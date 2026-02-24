<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\User;
use App\Models\Delivery;
use App\Models\RiderProfile;
use App\Models\Withdrawal;
use App\Models\SupportTicket;
use App\Models\Dispute;
use App\Models\SOSAlert;
use App\Models\AdminActivityLog;
use App\Models\Admin;
use App\Models\PricingConfig;
use App\Models\SystemSetting;
use App\Mail\KYCApprovedMail;
use App\Mail\KYCRejectedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{

    // --- Auth and Profile ---
    public function profile(Request $request)
    {
         return response()->json(['data' => $request->user()]);
    }

    // --- Dashboard ---
    public function stats()
    {
        return response()->json(['data' => [ 
            'total_users' => User::count(),
            'total_riders' => Rider::count(),
            'active_deliveries' => Delivery::whereIn('status', ['pending', 'in_transit', 'picked_up'])->count(),
            'total_revenue' => Delivery::where('status', 'delivered')->sum('platform_commission'),
            'pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
        ]]);
    }

    public function revenue(Request $request)
    {
        // Mock data for chart or aggregate real data
        // For last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $revenue = Delivery::where('status', 'delivered')
                ->where(function($q) use ($date) {
                    $q->whereDate('delivered_at', $date)
                      ->orWhere(function($sq) use ($date) {
                          $sq->whereNull('delivered_at')->whereDate('created_at', $date);
                      });
                })
                ->sum('platform_commission');
            $data[] = ['date' => $date, 'revenue' => $revenue];
        }
        return response()->json(['data' => $data]);
    }

    public function activities()
    {
        // 1. Get logs if any
        $logs = AdminActivityLog::latest()->take(10)->get()->map(function($log) {
            return [
                'id' => $log->id,
                'type' => 'kyc_approved',
                'description' => $log->description,
                'user' => 'Admin',
                'created_at' => $log->created_at
            ];
        });

        if ($logs->count() > 0) {
            return response()->json(['data' => $logs]);
        }

             $recentUsers = User::latest()->take(3)->get()->map(function($u) {
            return [
                'id' => 'u'.$u->id,
                'type' => 'user_registered',
                'description' => "New customer registered: {$u->name}",
                'created_at' => $u->created_at
            ];
        });

        $recentDeliveries = Delivery::where('status', 'delivered')->latest()->take(3)->get()->map(function($d) {
            return [
                'id' => 'd'.$d->id,
                'type' => 'delivery_completed',
                'description' => "Delivery #{$d->tracking_number} completed",
                'user' => $d->rider?->name ?? 'Rider',
                'created_at' => $d->delivered_at ?: $d->created_at
            ];
        });

        $activities = $recentUsers->concat($recentDeliveries)->sortByDesc('created_at')->values()->take(10);

        return response()->json(['data' => $activities]);
    }

    // --- Rider Management ---
    public function getRiders(Request $request)
    {
        $query = Rider::query();
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->kyc_status) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('verification_status', $request->kyc_status);
            });
        }
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
        }

        $riders = $query->with(['profile', 'wallet'])->latest()->paginate(15);
        return response()->json($riders);
    }

    public function getRider($id)
    {
        $rider = Rider::with(['profile', 'company'])->findOrFail($id);
        
        // 1. Recent Deliveries
        $recentDeliveries = $rider->deliveries()
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'tracking' => $delivery->tracking_number,
                    'customer' => $delivery->user ? $delivery->user->name : 'Unknown',
                    'date' => $delivery->created_at->format('Y-m-d'),
                    'amount' => (float) $delivery->total_price,
                    'earnings' => (float) ($delivery->rider_earning ?? 0), 
                    'status' => $delivery->status,
                    'rating' => $delivery->rating ? $delivery->rating->rating : null, // Assuming hasOne rating relationship on delivery
                ];
            });

        // 2. Ratings Breakdown
        $ratingsBreakdown = [];
        $totalRatings = $rider->ratings()->count();
        
        for ($i = 5; $i >= 1; $i--) {
            $count = $rider->ratings()->where('rating', $i)->count();
            $percentage = $totalRatings > 0 ? round(($count / $totalRatings) * 100) : 0;
            $ratingsBreakdown[] = [
                'stars' => $i,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        // 3. Earnings Stats
        $weeklyEarnings = $rider->deliveries()
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('rider_earning'); 

        $monthlyEarnings = $rider->deliveries()
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('rider_earning');
        
        $data = $rider->toArray();
        $data['details'] = [
            'recent_deliveries' => $recentDeliveries,
            'ratings_breakdown' => $ratingsBreakdown,
            'earnings' => [
                'week' => (float) $weeklyEarnings,
                'month' => (float) $monthlyEarnings,
                'total' => (float) $rider->total_earnings,
                'wallet' => (float) ($rider->wallet ? $rider->wallet->balance : 0)
            ]
        ];

        return response()->json(['data' => $data]);
    }

    public function pendingKycRiders()
    {
        // Frontend expects { data: Rider[] }
        return response()->json(['data' => Rider::whereHas('profile', function($q) {
            $q->where('verification_status', 'pending');
        })->with('profile')->get()]);
    }

    public function approveRiderKyc($id)
    {
        $rider = Rider::findOrFail($id);
        $rider->update(['status' => 'active']);
        
        if ($rider->profile) {
            $rider->profile->update([
                'verification_status' => 'approved', 
                'verified_at' => now(),
                'verified_by' => auth()->id()
            ]);
        }
        
        // Send approval email
        try {
            Mail::to($rider->email)->send(new KYCApprovedMail($rider->name));
        } catch (\Exception $e) {
            // \Log::error('Failed to send KYC approval email: ' . $e->getMessage());
        }

        return response()->json(['data' => $rider]);
    }
    
    public function rejectRiderKyc(Request $request, $id)
    {
        $rider = Rider::findOrFail($id);
        
        if ($rider->profile) {
            $rider->profile->update([
                'verification_status' => 'rejected', 
                'rejection_reason' => $request->reason
            ]);
        }
        // Rider status might remain pending or be set to rejected
        $rider->update(['status' => 'rejected']);

        // Send rejection email
        try {
            Mail::to($rider->email)->send(new KYCRejectedMail($rider->name, $request->reason));
        } catch (\Exception $e) {
            // \Log::error('Failed to send KYC rejection email: ' . $e->getMessage());
        }

        return response()->json(['data' => $rider]);
    }

    public function suspendRider($id)
    {
        $rider = Rider::findOrFail($id);
        $rider->update(['status' => 'suspended']);
        return response()->json(['data' => $rider]);
    }

    public function activateRider($id)
    {
        $rider = Rider::findOrFail($id);
        $rider->update(['status' => 'active']);
        return response()->json(['data' => $rider]);
    }

    // --- User Management ---
    public function getUsers(Request $request)
    {
        $query = User::query();

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
             $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
        }

        return response()->json($query->with('wallet')->latest()->paginate(15));
    }

    public function getUser($id)
    {
        return response()->json(['data' => User::with('wallet')->findOrFail($id)]);
    }

    public function suspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'suspended']);
        return response()->json(['data' => $user]);
    }

    public function activateUser($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);
        return response()->json(['data' => $user]);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['success' => true]);
    }
    
    // --- Finance ---
    public function getWithdrawals(Request $request)
    {
        $query = Withdrawal::with('withdrawable');
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        return response()->json($query->latest()->paginate(15));
    }
    
    public function approveWithdrawal($id)
    {
        $withdrawal = Withdrawal::findOrFail($id);
        
        // In a real app, trigger Paystack transfer here
        
        $withdrawal->update(['status' => 'completed', 'processed_at' => now()]);
        
        return response()->json(['data' => $withdrawal]);
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);
        $withdrawal->update(['status' => 'rejected', 'rejection_reason' => $request->reason]);
        
        // Refund wallet
        $wallet = $withdrawal->wallet;
        $wallet->balance += $withdrawal->amount;
        $wallet->save();
        
        return response()->json(['data' => $withdrawal]);
    }

    // --- Deliveries ---
    public function getDeliveries(Request $request)
    {
        $query = Delivery::with(['user', 'rider']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->rider_id) {
            $query->where('rider_id', $request->rider_id);
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->where('tracking_number', 'like', "%{$request->search}%");
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function getChatMessages($id)
    {
        $delivery = Delivery::findOrFail($id);
        $chat = $delivery->chat;

        if (!$chat) {
            return response()->json(['data' => []]);
        }

        $messages = $chat->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $messages]);
    }
    
    // --- Pricing ---
    public function getPricing()
    {
        $config = PricingConfig::where('is_active', true)->latest()->first();
        
        if (!$config) {
            return response()->json(['data' => null]);
        }

        // Map database column names to frontend field names
        $data = [
            'base_price' => $config->base_price,
            'price_per_km_first_5' => $config->price_per_km_first_5,
            'price_per_km_after_5' => $config->price_per_km_after_5,
            'small_package_fee' => $config->small_package_fee,
            'medium_package_fee' => $config->medium_package_fee,
            'large_package_fee' => $config->large_package_fee,
            'rush_hour_multiplier' => $config->rush_hour_multiplier,
            'rush_hour_start_time' => $config->rush_hour_start_time,
            'rush_hour_end_time' => $config->rush_hour_end_time,
            'evening_rush_start_time' => $config->evening_rush_start_time,
            'evening_rush_end_time' => $config->evening_rush_end_time,
            'night_fee_multiplier' => $config->night_fee_multiplier,
            'night_start_time' => $config->night_start_time,
            'night_end_time' => $config->night_end_time,
            'express_multiplier' => $config->express_multiplier,
            // Map database column to frontend field name
            'rider_commission_percentage' => $config->default_commission_percentage,
            'company_commission_percentage' => $config->company_commission_percentage,
            'rider_search_radius_km' => $config->rider_search_radius_km,
            'max_delivery_distance_km' => $config->max_delivery_distance_km,
            'min_withdrawal_amount' => $config->min_withdrawal_amount,
            'max_withdrawal_amount' => $config->max_withdrawal_amount,
        ];

        return response()->json(['data' => $data]);
    }

    public function updatePricing(Request $request)
    {
        $all = $request->all();
        
        // Map frontend fields to database fields correctly based on migration
        $data = [
            'base_price' => $all['base_price'] ?? 500,
            'price_per_km_first_5' => $all['price_per_km_first_5'] ?? $all['price_per_km'] ?? 100,
            'price_per_km_after_5' => $all['price_per_km_after_5'] ?? $all['price_per_km'] ?? 80,
            'small_package_fee' => $all['small_package_fee'] ?? 0,
            'medium_package_fee' => $all['medium_package_fee'] ?? 0,
            'large_package_fee' => $all['large_package_fee'] ?? 0,
            'rush_hour_multiplier' => $all['rush_hour_multiplier'] ?? 1.20,
            'rush_hour_start_time' => $all['rush_hour_start_time'] ?? '07:00:00',
            'rush_hour_end_time' => $all['rush_hour_end_time'] ?? '09:00:00',
            'evening_rush_start_time' => $all['evening_rush_start_time'] ?? '16:00:00',
            'evening_rush_end_time' => $all['evening_rush_end_time'] ?? '19:00:00',
            'night_fee_multiplier' => $all['night_fee_multiplier'] ?? 1.50,
            'night_start_time' => $all['night_start_time'] ?? '22:00:00',
            'night_end_time' => $all['night_end_time'] ?? '05:00:00',
            'express_multiplier' => $all['express_multiplier'] ?? 1.30,
            // Map frontend field name to database column
            'default_commission_percentage' => $all['rider_commission_percentage'] ?? 70,
            'company_commission_percentage' => $all['company_commission_percentage'] ?? 10,
            'rider_search_radius_km' => $all['rider_search_radius_km'] ?? 5.0,
            'max_delivery_distance_km' => $all['max_delivery_distance_km'] ?? 50.0,
            'min_withdrawal_amount' => $all['min_withdrawal_amount'] ?? 1000,
            'max_withdrawal_amount' => $all['max_withdrawal_amount'] ?? 100000,
            'effective_from' => now(),
            'is_active' => true,
        ];

        // Deactivate all previous configs
        PricingConfig::query()->update(['is_active' => false]);
        
        // Create new active config
        $config = PricingConfig::create($data);
        
        // Return mapped data to frontend
        $responseData = [
            'base_price' => $config->base_price,
            'price_per_km_first_5' => $config->price_per_km_first_5,
            'price_per_km_after_5' => $config->price_per_km_after_5,
            'small_package_fee' => $config->small_package_fee,
            'medium_package_fee' => $config->medium_package_fee,
            'large_package_fee' => $config->large_package_fee,
            'rush_hour_multiplier' => $config->rush_hour_multiplier,
            'rush_hour_start_time' => $config->rush_hour_start_time,
            'rush_hour_end_time' => $config->rush_hour_end_time,
            'evening_rush_start_time' => $config->evening_rush_start_time,
            'evening_rush_end_time' => $config->evening_rush_end_time,
            'night_fee_multiplier' => $config->night_fee_multiplier,
            'night_start_time' => $config->night_start_time,
            'night_end_time' => $config->night_end_time,
            'express_multiplier' => $config->express_multiplier,
            'rider_commission_percentage' => $config->default_commission_percentage,
            'company_commission_percentage' => $config->company_commission_percentage,
            'rider_search_radius_km' => $config->rider_search_radius_km,
            'max_delivery_distance_km' => $config->max_delivery_distance_km,
            'min_withdrawal_amount' => $config->min_withdrawal_amount,
            'max_withdrawal_amount' => $config->max_withdrawal_amount,
        ];
        
        return response()->json(['data' => $responseData]);
    }

    // --- Settings ---
    public function getSettings()
    {
        // Return key-value pairs or structured object
        $settings = SystemSetting::all()->pluck('value', 'key');
        return response()->json(['data' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        
        return response()->json(['success' => true]);
    }

    // --- Support Tickets ---
    public function getSupportTickets(Request $request)
    {
         $query = SupportTicket::with('user');

         if ($request->status) {
             $query->where('status', $request->status);
         }
         
         return response()->json($query->latest()->paginate(15));
    }

    public function replySupportTicket(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        
        $ticket->messages()->create([
            'sender_type' => 'App\Models\Admin',
            'sender_id' => auth()->id(),
            'message' => $request->message,
        ]);
        
        if ($request->status) {
            $ticket->update(['status' => $request->status]);
        }
        
        return response()->json(['data' => $ticket->load('messages')]);
    }

    // --- Disputes ---
    public function getDisputes(Request $request)
    {
        $query = Dispute::with('delivery');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function resolveDispute(Request $request, $id)
    {
        $dispute = Dispute::findOrFail($id);
        
        $dispute->update([
            'status' => 'resolved',
            'resolution' => $request->resolution,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'refund_amount' => $request->refund_amount ?? 0,
        ]);
        
        // logic to refund wallet if amount > 0
        if ($request->refund_amount > 0) {
             // ...
        }

        return response()->json(['data' => $dispute]);
    }

    // --- Analytics ---
    public function analytics(Request $request)
    {
        $period = $request->query('period', 'week');
        $fromDate = $this->getFromDate($period);

        // 1. Revenue Analytics
        $revenueByMethod = Delivery::where('status', 'delivered')
            ->where('created_at', '>=', $fromDate)
            ->selectRaw('payment_method as method, sum(total_price) as amount')
            ->groupBy('payment_method')
            ->get();

        $revenueByPackage = Delivery::where('status', 'delivered')
            ->where('created_at', '>=', $fromDate)
            ->selectRaw('package_size as size, sum(total_price) as amount')
            ->groupBy('package_size')
            ->get();

        // 2. Delivery Analytics
        $deliveriesByStatus = Delivery::where('created_at', '>=', $fromDate)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        // 3. Rider Performance
        $topRiders = Rider::withCount(['deliveries' => function ($query) use ($fromDate) {
                $query->where('status', 'delivered')->where('created_at', '>=', $fromDate);
            }])
            ->withSum(['deliveries as earnings' => function ($query) use ($fromDate) {
                $query->where('status', 'delivered')->where('created_at', '>=', $fromDate);
            }], 'rider_earning')
            ->withAvg('ratings', 'rating')
            ->orderByDesc('deliveries_count')
            ->take(5)
            ->get()
            ->map(function ($rider) {
                return [
                    'name' => $rider->name,
                    'deliveries' => $rider->deliveries_count,
                    'earnings' => (float) ($rider->earnings ?? 0),
                    'rating' => (float) round($rider->ratings_avg_rating ?? 0, 1)
                ];
            });

        // 4. Data (Chart)
        $chartData = $this->getChartData($period, $fromDate);

        // 5. Overview Stats
        $totalRevenue = Delivery::where('status', 'delivered')->where('created_at', '>=', $fromDate)->sum('total_price');
        $prevFromDate = $this->getPrevFromDate($period, $fromDate);
        $prevRevenue = Delivery::where('status', 'delivered')
            ->where('created_at', '>=', $prevFromDate)
            ->where('created_at', '<', $fromDate)
            ->sum('total_price');
        
        $revenueChange = $prevRevenue > 0 ? (($totalRevenue - $prevRevenue) / $prevRevenue) * 100 : 100;

        $totalDeliveries = Delivery::where('created_at', '>=', $fromDate)->count();
        $prevDeliveries = Delivery::where('created_at', '>=', $prevFromDate)
            ->where('created_at', '<', $fromDate)
            ->count();
        $deliveriesChange = $prevDeliveries > 0 ? (($totalDeliveries - $prevDeliveries) / $prevDeliveries) * 100 : 100;

        return response()->json(['data' => [
            'overview' => [
                'total_revenue' => (float) $totalRevenue,
                'revenue_change' => (float) $revenueChange,
                'total_deliveries' => $totalDeliveries,
                'deliveries_change' => (float) $deliveriesChange,
                'active_riders' => Rider::where('status', 'active')->count(),
                'avg_rating' => (float) round(\App\Models\Rating::avg('rating') ?: 0, 1),
                'total_riders_count' => Rider::count(),
            ],
            'revenue_by_method' => $revenueByMethod,
            'revenue_by_package' => $revenueByPackage,
            'deliveries_by_status' => $deliveriesByStatus,
            'top_riders' => $topRiders,
            'chart_data' => $chartData
        ]]);
    }

    private function getFromDate($period)
    {
        return match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->subDays(7),
        };
    }

    private function getPrevFromDate($period, $fromDate)
    {
        return match ($period) {
            'today' => $fromDate->copy()->subDay(),
            'week' => $fromDate->copy()->subWeek(),
            'month' => $fromDate->copy()->subMonth(),
            'year' => $fromDate->copy()->subYear(),
            default => $fromDate->copy()->subDays(7),
        };
    }

    private function getChartData($period, $fromDate)
    {
        $data = [];
        $steps = match ($period) {
            'today' => 24,
            'week' => 7,
            'month' => now()->daysInMonth,
            'year' => 12,
            default => 7,
        };

        for ($i = 0; $i < $steps; $i++) {
            $currentDate = match ($period) {
                'today' => $fromDate->copy()->addHours($i),
                'week' => $fromDate->copy()->addDays($i),
                'month' => $fromDate->copy()->addDays($i),
                'year' => $fromDate->copy()->addMonths($i),
                default => $fromDate->copy()->addDays($i),
            };

            $label = match ($period) {
                'today' => $currentDate->format('H:00'),
                'week' => $currentDate->format('D'),
                'month' => $currentDate->format('d M'),
                'year' => $currentDate->format('M'),
                default => $currentDate->format('Y-m-d'),
            };

            $revenue = Delivery::where('status', 'delivered')
                ->where(function($q) use ($currentDate, $period) {
                    if ($period === 'today') {
                        $q->whereBetween('created_at', [$currentDate, $currentDate->copy()->addHour()]);
                    } elseif ($period === 'year') {
                        $q->whereMonth('created_at', $currentDate->month)->whereYear('created_at', $currentDate->year);
                    } else {
                        $q->whereDate('created_at', $currentDate->format('Y-m-d'));
                    }
                })
                ->sum('total_price');

            $data[] = ['label' => $label, 'revenue' => (float) $revenue];
        }

        return $data;
    }
    // --- SOS Alerts ---
    public function getSOSAlerts(Request $request)
    {
        $query = SOSAlert::with('rider');
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        return response()->json(['data' => $query->latest()->paginate(15)]);
    }

    public function resolveSOSAlert($id)
    {
        $alert = SOSAlert::findOrFail($id);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        
        return response()->json(['data' => $alert]);
    }

    // --- Support Messages ---
    public function getSupportTicket($id)
    {
        $ticket = SupportTicket::with('user')->findOrFail($id);
        
        $userName = 'Unknown';
        $userEmail = 'N/A';
        
        if ($ticket->user) {
             $userName = $ticket->user->name ?? ($ticket->user->first_name . ' ' . $ticket->user->last_name);
             $userEmail = $ticket->user->email;
        }

        return response()->json(['data' => [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'description' => $ticket->description ?? $ticket->message,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'created_at' => $ticket->created_at,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'user_id' => $ticket->user_id,
            'user_type' => $ticket->user_type,
        ]]);
    }

    public function getSupportMessages($id)
    {
        $ticket = SupportTicket::findOrFail($id);
        
        $messages = $ticket->messages()
            ->with('sender') 
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($msg) {
                $senderName = 'Unknown';
                if ($msg->sender_type === 'Admin') {
                    $senderName = 'Support Agent';
                } elseif ($msg->sender_type === 'User') {
                    $senderName = $msg->ticket->user->name ?? 'User';
                } elseif ($msg->sender_type === 'Rider') {
                    $senderName = $msg->ticket->user->name ?? 'Rider';
                }

                return [
                    'id' => $msg->id,
                    'sender_type' => $msg->sender_type === 'Admin' ? 'admin' : 'user',
                    'sender_name' => $senderName,
                    'content' => $msg->message,
                    'created_at' => $msg->created_at,
                    'attachments' => $msg->attachments ?? []
                ];
            });

        return response()->json(['data' => $messages]);
    }

    // --- Dispute Messages ---
    public function getDisputeMessages($id)
    {
        $dispute = Dispute::findOrFail($id);
        
        $messages = $dispute->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($msg) {
                $senderName = 'Unknown';
                 if ($msg->sender_type === 'Admin' || $msg->sender_type === 'admin') { // fast fix for varying conventions
                    $senderName = 'Admin';
                } elseif (str_contains($msg->sender_type, 'User')) {
                    // Fetch user name? For now simplified.
                     $senderName = 'Customer';
                } elseif (str_contains($msg->sender_type, 'Rider')) {
                    $senderName = 'Rider';
                }

                return [
                    'id' => $msg->id,
                    'sender_type' => ($msg->sender_type === 'Admin' || $msg->sender_type === 'admin') ? 'admin' : 'user',
                    'sender_name' => $senderName,
                    'content' => $msg->message,
                    'created_at' => $msg->created_at,
                    'attachments' => $msg->attachments ?? []
                ];
            });

        return response()->json(['data' => $messages]);
    }

    public function sendDisputeMessage(Request $request, $id)
    {
        $dispute = Dispute::findOrFail($id);
        
        $message = $dispute->messages()->create([
            'sender_type' => 'Admin',
            'sender_id' => auth()->id(),
            'message' => $request->message,
            'attachments' => $request->attachments ?? []
        ]);
        
        return response()->json(['data' => $message]);
    }
}
