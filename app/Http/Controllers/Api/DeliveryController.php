<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Services\DeliveryMatchingService;
use App\Services\MapboxService;
use App\Services\ImageUploadService;
use App\Services\NotificationService;
use App\Services\OSRMService;
use App\Services\PaystackService;
use App\Services\PricingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    protected $pricingService;
    protected $walletService;
    protected $matchingService;
    protected $mapboxService;
    protected $osrmService;
    protected $imageService;
    protected $paystackService;
    protected $notificationService;

    public function __construct(
        PricingService $pricingService,
        WalletService $walletService,
        DeliveryMatchingService $matchingService,
        MapboxService $mapboxService,
        OSRMService $osrmService,
        ImageUploadService $imageService,
        PaystackService $paystackService
    ) {
        $this->pricingService = $pricingService;
        $this->walletService = $walletService;
        $this->matchingService = $matchingService;
        $this->mapboxService = $mapboxService;
        $this->osrmService = $osrmService;
        $this->imageService = $imageService;
        $this->paystackService = $paystackService;
        $this->notificationService = app(NotificationService::class);
    }
    public function requestDelivery(Request $request)
    {
        $request->validate([
            'pickup_address' => 'required|string',
            'pickup_latitude' => 'required|numeric',
            'pickup_longitude' => 'required|numeric',
            'dropoff_address' => 'required|string',
            'dropoff_latitude' => 'required|numeric',
            'dropoff_longitude' => 'required|numeric',
            'package_size' => 'required|in:small,medium,large',
            'urgency' => 'nullable|in:normal,express',
            'pickup_contact_name' => 'required|string',
            'pickup_contact_phone' => 'required|string',
            'pickup_instructions' => 'nullable|string',
            'dropoff_contact_name' => 'required|string',
            'dropoff_contact_phone' => 'required|string',
            'dropoff_instructions' => 'nullable|string',
            'package_description' => 'required|string',
            'package_value' => 'required|numeric',
        ]);
        
        $user = $request->user();

        // 1. Calculate Price Again (Security)
        $routeData = $this->mapboxService->getDistanceAndDuration(
            $request->pickup_latitude, $request->pickup_longitude, 
            $request->dropoff_latitude, $request->dropoff_longitude
        );
        $estimate = $this->pricingService->calculateDeliveryPrice(
            $routeData['distance_km'], 
            $request->package_size, 
            $request->urgency ?? 'normal'
        );
        
        // 2. Handle Payment logic (Wallet or Saved Card)
        $totalPrice = $estimate['total_price'];
        
        if ($request->payment_method === 'card' && $request->payment_method_id) {
            $paymentMethod = \App\Models\PaymentMethod::where('user_id', $user->id)
                ->where('id', $request->payment_method_id)
                ->firstOrFail();

            $charge = $this->paystackService->chargeAuthorization(
                $user->email,
                $totalPrice,
                $paymentMethod->paystack_authorization_code,
                ['user_id' => $user->id, 'type' => 'delivery_payment']
            );

            if (!$charge['status'] || $charge['data']['status'] !== 'success') {
                return response()->json([
                    'success' => false, 
                    'message' => 'Card payment failed',
                    'data' => $charge
                ], 400);
            }
            
            // Credit wallet first (for record)
            $this->walletService->credit(
                $user->wallet,
                $totalPrice,
                'funding',
                "Funding for delivery " . ($delivery_number_placeholder ?? 'New Delivery'),
                ['reference' => $charge['data']['reference']]
            );
        } else if ($request->payment_method === 'cash') {
            // Cash payment - no upfront wallet deduction
        } else {
            // Default to Wallet
            if ($user->wallet->balance < $totalPrice) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 400);
            }
        }

        // 3. Create Delivery & Debit
        $delivery = DB::transaction(function () use ($user, $request, $estimate, $routeData, $totalPrice) {
            $delivery = Delivery::create([
                'tracking_number' => 'SR-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'user_id' => $user->id,
                'status' => 'pending',
                'pickup_address' => $request->pickup_address,
                'pickup_latitude' => $request->pickup_latitude,
                'pickup_longitude' => $request->pickup_longitude,
                'pickup_contact_name' => $request->pickup_contact_name,
                'pickup_contact_phone' => $request->pickup_contact_phone,
                
                'dropoff_address' => $request->dropoff_address,
                'dropoff_latitude' => $request->dropoff_latitude,
                'dropoff_longitude' => $request->dropoff_longitude,
                'dropoff_contact_name' => $request->dropoff_contact_name,
                'dropoff_contact_phone' => $request->dropoff_contact_phone,
                'dropoff_instructions' => $request->dropoff_instructions,
                'pickup_instructions' => $request->pickup_instructions,

                'package_size' => $request->package_size,
                'package_description' => $request->package_description,
                'package_value' => $request->package_value,
                
                'distance_km' => $routeData['distance_km'],
                'base_price' => $estimate['base_price'],
                'distance_price' => $estimate['distance_price'],
                'size_fee' => $estimate['size_fee'],
                'time_fee' => $estimate['time_fee'],
                'urgency' => $request->urgency ?? 'normal',
                'urgency_multiplier' => $estimate['urgency_multiplier'],
                'total_price' => $estimate['total_price'],
                
                // Commissions (will be updated when rider assigned, for now assume individual rider config or defaults)
                'platform_commission' => 0, // Placeholder
                'rider_earning' => 0, 
                
                'actual_duration_minutes' => null,
                'payment_method' => $request->payment_method ?? 'wallet',
                'payment_status' => ($request->payment_method === 'card' ? 'paid' : 'pending'),
                'delivery_pin' => rand(1000, 9999), 
            ]);
            
            // Debit Log (Only if using wallet or if we want to track the expense in the wallet history)
            if ($delivery->payment_method === 'wallet') {
                $this->walletService->debit(
                    $user->wallet, 
                    $totalPrice, 
                    'delivery_payment',
                    "Payment for delivery {$delivery->tracking_number}"
                );
                $delivery->update(['payment_status' => 'paid']);
            }

            return $delivery;
        });

        // 4. Find Riders
        $riders = $this->matchingService->findNearestRiders($request->pickup_latitude, $request->pickup_longitude);
        $this->matchingService->broadcastToRiders($delivery, $riders);

        return response()->json(['success' => true, 'data' => $delivery]);
    }

    public function acceptDelivery(Request $request, $id)
    {
        $rider = $request->user();
        if ($rider->status !== 'active') {
            return response()->json([
                'success' => false, 
                'message' => 'Account not active. Please complete KYC verification to accept deliveries.'
            ], 403);
        }
        
        $delivery = Delivery::findOrFail($id);
        
        if ($delivery->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Delivery no longer available'], 400);
        }

        // Calculate commissions based on THIS rider
        $commissions = $this->pricingService->calculateCommissions($delivery->total_price, $rider);

        $delivery->update([
            'status' => 'rider_accepted',
            'rider_id' => $rider->id,
            'company_id' => $rider->company_id,
            'rider_accepted_at' => now(),
            'platform_commission' => $commissions['platform_commission'],
            'company_commission' => $commissions['company_commission'],
            'rider_earning' => $commissions['rider_earning'],
        ]);

        // Create Chat immediately upon acceptance
        $delivery->chat()->firstOrCreate(['delivery_id' => $delivery->id]);

        $delivery->statusLogs()->create([
            'status' => 'rider_accepted',
            'created_by_type' => 'rider',
            'created_by_id' => $rider->id
        ]);
        
        // Notify User
        if ($delivery->user) {
            $this->notificationService->sendToUser(
                $delivery->user, 
                'Rider Assigned', 
                "{$rider->name} has accepted your delivery request and is on the way.",
                ['type' => 'delivery_accepted', 'delivery_id' => $delivery->id]
            );
        }

        return response()->json([
            'success' => true, 
            'message' => 'Delivery accepted',
            'data' => $delivery->fresh() // Return fresh delivery data
        ]);
    }

    public function available(Request $request)
    {
        // Require location for accurate "Algorithmic Assignment"
        $lat = $request->query('latitude');
        $lng = $request->query('longitude');
        $radiusKm = 15; // Riders see orders within 15km

        $query = Delivery::where('status', 'pending');

        if ($lat && $lng) {
            // Filter by Haversine distance
            $query->select('*')
                ->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(pickup_latitude)) * cos(radians(pickup_longitude) - radians(?)) + sin(radians(?)) * sin(radians(pickup_latitude)))) AS distance", [$lat, $lng, $lat])
                ->having('distance', '<', $radiusKm)
                ->orderBy('distance');
        } else {
            // Fallback: If no location sent, show latest (though rider app should send location)
            $query->latest();
        }

        $deliveries = $query->paginate(20);
        return response()->json(['success' => true, 'data' => $deliveries]);
    }

    public function markPickedUp(Request $request, $id)
    {
        $delivery = Delivery::where('id', $id)->where('rider_id', $request->user()->id)->firstOrFail();
        
        if ($delivery->status !== 'rider_accepted') {
             return response()->json(['success' => false, 'message' => 'Invalid status transition'], 400);
        }

        $photoUrl = null;
        if ($request->photo) {
            $photoUrl = $this->imageService->upload($request->photo, "deliveries/{$delivery->id}/pickup");
        }

        $delivery->update([
            'status' => 'picked_up', 
            'picked_up_at' => now(),
            'pickup_proof_photo' => $photoUrl
        ]);
        
        $delivery->statusLogs()->create([
            'status' => 'picked_up',
            'created_by_type' => 'rider',
            'created_by_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Delivery picked up',
            'data' => $delivery->fresh()
        ]);
    }

    public function markDelivered(Request $request, $id)
    {
        $request->validate([
            'delivery_pin' => 'required|string|size:4',
            'photo' => 'required|image|max:5120', // 5MB max
        ]);
        
        $delivery = Delivery::where('id', $id)->where('rider_id', $request->user()->id)->firstOrFail();
        
        if ($delivery->status !== 'picked_up' && $delivery->status !== 'in_transit' && $delivery->status !== 'arrived') {
             return response()->json(['success' => false, 'message' => 'Invalid status transition'], 400);
        }

        // Validate PIN
        if ($delivery->delivery_pin != $request->delivery_pin) {
            return response()->json(['success' => false, 'message' => 'Invalid delivery PIN. Please ask the customer for the correct 4-digit code.'], 400);
        }

        $photoUrl = null;
        if ($request->photo) {
            $photoUrl = $this->imageService->upload($request->photo, "deliveries/{$delivery->id}/dropoff");
        }

        DB::transaction(function () use ($delivery, $request, $photoUrl) {
            $delivery->update([
                'status' => 'delivered', 
                'delivered_at' => now(),
                'delivery_proof_photo' => $photoUrl
            ]);
            
            $delivery->statusLogs()->create([
                'status' => 'delivered',
                'created_by_type' => 'rider',
                'created_by_id' => $request->user()->id
            ]);

            // Credit Rider Wallet
            $rider = $request->user();
            $this->walletService->credit(
                $rider->wallet,
                $delivery->rider_earning,
                'delivery_earning',
                "Earning for delivery {$delivery->tracking_number}"
            );

            // Credit Company Wallet (if applicable)
            if ($delivery->company_id && $delivery->company_commission > 0) {
                 $company = \App\Models\Company::find($delivery->company_id);
                 if ($company && $company->wallet) {
                     $this->walletService->credit(
                        $company->wallet,
                        $delivery->company_commission,
                        'commission',
                        "Commission for delivery {$delivery->tracking_number}"
                    );
                }
            }

            // Close Chat
            if ($delivery->chat) {
                $delivery->chat->update(['status' => 'closed']);
            }
        });

        return response()->json([
            'success' => true, 
            'message' => 'Delivery completed',
            'data' => $delivery->fresh()
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        if ($user instanceof \App\Models\User) {
            $deliveries = $user->deliveries()->latest()->paginate(20);
        } elseif ($user instanceof \App\Models\Rider) {
            $deliveries = $user->assignedDeliveries()->latest()->paginate(20);
        } else {
             $deliveries = [];
        }
        
        return response()->json(['success' => true, 'data' => $deliveries]);
    }
    
    public function getActive(Request $request)
    {
        $delivery = Delivery::where('rider_id', $request->user()->id)
            ->whereIn('status', ['rider_accepted', 'picked_up', 'in_transit', 'arrived'])
            ->with(['user', 'statusLogs'])
            ->first();

        // Ensure we return proper structure even if null
        return response()->json([
            'success' => true, 
            'data' => $delivery
        ]);
    }

    public function startTransit(Request $request, $id)
    {
        $delivery = Delivery::where('id', $id)->where('rider_id', $request->user()->id)->firstOrFail();
        
        if ($delivery->status !== 'picked_up') {
            return response()->json(['success' => false, 'message' => 'Invalid status transition'], 400);
        }

        $delivery->update([
            'status' => 'in_transit',
            'started_transit_at' => now()
        ]);

        $delivery->statusLogs()->create([
            'status' => 'in_transit',
            'created_by_type' => 'rider',
            'created_by_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Started transit',
            'data' => $delivery->fresh()
        ]);
    }

    public function markArrived(Request $request, $id)
    {
        $delivery = Delivery::where('id', $id)->where('rider_id', $request->user()->id)->firstOrFail();
        
        if ($delivery->status !== 'in_transit') {
            return response()->json(['success' => false, 'message' => 'Invalid status transition'], 400);
        }

        $delivery->update([
            'status' => 'arrived',
            'arrived_at' => now()
        ]);

        $delivery->statusLogs()->create([
            'status' => 'arrived',
            'created_by_type' => 'rider',
            'created_by_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Marked as arrived',
            'data' => $delivery->fresh()
        ]);
    }

    public function markFailed(Request $request, $id)
    {
        $request->validate(['failed_reason' => 'required|string']);
        
        $delivery = Delivery::where('id', $id)->where('rider_id', $request->user()->id)->firstOrFail();
        
        $delivery->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failed_reason' => $request->failed_reason
        ]);

        $delivery->statusLogs()->create([
            'status' => 'failed',
            'comment' => $request->failed_reason,
            'created_by_type' => 'rider',
            'created_by_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Delivery marked as failed',
            'data' => $delivery->fresh()
        ]);
    }

    public function show($id)
    {
        $delivery = Delivery::with(['rider', 'statusLogs'])->findOrFail($id);
        // Authorization check...
        return response()->json(['success' => true, 'data' => $delivery]);
    }

    /**
     * Track delivery - Get real-time tracking information
     */
    public function track(Request $request, $id)
    {
        $user = $request->user();
        
        $delivery = Delivery::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['rider', 'statusLogs'])
            ->firstOrFail();

        $trackingData = [
            'delivery' => $delivery,
            'current_status' => $delivery->status,
            'tracking_number' => $delivery->tracking_number,
            'estimated_delivery_time' => $delivery->estimated_duration_minutes,
        ];

        // Get rider's current location if delivery is active
        if ($delivery->rider_id && in_array($delivery->status, ['rider_accepted', 'picked_up', 'in_transit', 'arrived'])) {
            $riderLocation = \App\Models\RiderLocation::where('rider_id', $delivery->rider_id)
                ->latest()
                ->first();

            if ($riderLocation) {
                $trackingData['rider_location'] = [
                    'latitude' => $riderLocation->latitude,
                    'longitude' => $riderLocation->longitude,
                    'updated_at' => $riderLocation->updated_at,
                ];

                // Calculate ETA based on current location
                // Calculate ETA based on current location
                if ($delivery->status === 'in_transit' || $delivery->status === 'arrived' || $delivery->status === 'rider_accepted') {
                    $routeData = $this->mapboxService->getDistanceAndDuration(
                        $riderLocation->latitude,
                        $riderLocation->longitude,
                        $delivery->status === 'rider_accepted' ? $delivery->pickup_latitude : $delivery->dropoff_latitude,
                        $delivery->status === 'rider_accepted' ? $delivery->pickup_longitude : $delivery->dropoff_longitude
                    );

                    $trackingData['eta_minutes'] = $routeData['duration_min'] ?? null;
                    $trackingData['distance_remaining_km'] = $routeData['distance_km'] ?? null;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $trackingData
        ]);
    }

    /**
     * Cancel delivery - User can cancel with automatic refund
     */
    public function cancelDelivery(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $user = $request->user();
        
        $delivery = Delivery::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Only allow cancellation for pending or assigned deliveries
        // Per user request: Once accepted and rider is on the way, cancellation is not allowed.
        if (!in_array($delivery->status, ['pending', 'assigned'])) {
            return response()->json([
                'success' => false,
                'message' => 'Rider has already accepted your delivery. Cancellation is no longer allowed via the app.'
            ], 400);
        }

        DB::transaction(function () use ($delivery, $request, $user) {
            // Update delivery status
            $delivery->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->reason,
                'cancelled_by' => 'user',
            ]);

            // Create status log
            $delivery->statusLogs()->create([
                'status' => 'cancelled',
                'comment' => $request->reason,
                'created_by_type' => 'user',
                'created_by_id' => $user->id
            ]);

            // Process refund if payment was made
            if ($delivery->payment_status === 'paid') {
                $refundAmount = $delivery->total_price;

                // If rider already accepted, deduct a small cancellation fee (optional)
                if ($delivery->status === 'rider_accepted') {
                    $cancellationFee = $delivery->total_price * 0.1; // 10% cancellation fee
                    $refundAmount = $delivery->total_price - $cancellationFee;
                }

                // Credit refund to user's wallet
                $this->walletService->credit(
                    $user->wallet,
                    $refundAmount,
                    'refund',
                    "Refund for cancelled delivery {$delivery->tracking_number}"
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Delivery cancelled successfully. Refund has been processed to your wallet.',
            'data' => $delivery->fresh()
        ]);
    }
}