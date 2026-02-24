<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Services\DeliveryMatchingService;
use App\Services\GoogleMapsService;
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
    protected $googleMaps;

    public function __construct(
        PricingService $pricingService,
        WalletService $walletService,
        DeliveryMatchingService $matchingService,
        GoogleMapsService $googleMaps
    ) {
        $this->pricingService = $pricingService;
        $this->walletService = $walletService;
        $this->matchingService = $matchingService;
        $this->googleMaps = $googleMaps;
    }

    public function requestDelivery(Request $request)
    {
        $request->validate([
            'pickup_address' => 'required',
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'dropoff_lat' => 'required|numeric',
            'package_size' => 'required',
            // ...
        ]);
        
        $user = $request->user();

        // 1. Calculate Price Again (Security)
        $routeData = $this->googleMaps->getDistanceAndDuration(
            $request->pickup_lat, $request->pickup_lng, 
            $request->dropoff_lat, $request->dropoff_lng
        );
        $estimate = $this->pricingService->calculateDeliveryPrice(
            $routeData['distance_km'], 
            $request->package_size, 
            $request->urgency ?? 'normal'
        );
        
        // 2. Check Wallet
        if ($user->wallet->balance < $estimate['total_price']) {
            return response()->json(['success' => false, 'message' => 'Insufficient funds'], 400);
        }

        // 3. Create Delivery & Debit
        $delivery = DB::transaction(function () use ($user, $request, $estimate, $routeData) {
            $delivery = Delivery::create([
                'tracking_number' => 'SR-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'user_id' => $user->id,
                'status' => 'pending',
                'pickup_address' => $request->pickup_address,
                'pickup_latitude' => $request->pickup_lat,
                'pickup_longitude' => $request->pickup_lng,
                'pickup_contact_name' => $request->pickup_contact_name,
                'pickup_contact_phone' => $request->pickup_contact_phone,
                
                'dropoff_address' => $request->dropoff_address,
                'dropoff_latitude' => $request->dropoff_lat,
                'dropoff_longitude' => $request->dropoff_lng,
                'dropoff_contact_name' => $request->dropoff_contact_name,
                'dropoff_contact_phone' => $request->dropoff_contact_phone,

                'package_size' => $request->package_size,
                
                'distance_km' => $routeData['distance_km'],
                'base_price' => $estimate['base_price'],
                'distance_price' => $estimate['distance_price'],
                'size_fee' => $estimate['size_fee'],
                'time_fee' => $estimate['time_fee'],
                'urgency_multiplier' => $estimate['urgency_multiplier'],
                'total_price' => $estimate['total_price'],
                
                // Commissions (will be updated when rider assigned, for now assume individual rider config or defaults)
                'platform_commission' => 0, // Placeholder
                'rider_earning' => 0, 
                
                'delivery_pin' => rand(100000, 999999),
                'estimated_duration_minutes' => $routeData['duration_min']
            ]);
            
            // Debit Log
            $this->walletService->debit(
                $user->wallet, 
                $estimate['total_price'], 
                'delivery_payment',
                "Payment for delivery {$delivery->tracking_number}"
            );

            return $delivery;
        });

        // 4. Find Riders
        $riders = $this->matchingService->findNearestRiders($request->pickup_lat, $request->pickup_lng);
        $this->matchingService->broadcastToRiders($delivery, $riders);

        return response()->json(['success' => true, 'data' => $delivery]);
    }

    public function acceptDelivery(Request $request, $id)
    {
        $rider = $request->user();
        if ($rider->status !== 'active') return response()->json(['success' => false, 'message' => 'Rider not active'], 403);
        
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

        $delivery->statusLogs()->create([
            'status' => 'rider_accepted',
            'created_by_type' => 'rider',
            'created_by_id' => $rider->id
        ]);
        
        // Notify User...

        return response()->json(['success' => true, 'message' => 'Delivery accepted']);
    }

    public function available(Request $request)
    {
         // Logic to find deliveries near rider
         // For now, return pending deliveries in same city? Or just all pending (simplification)
         $deliveries = Delivery::where('status', 'pending')->latest()->paginate(20);
         return response()->json(['success' => true, 'data' => $deliveries]);
    }

    public function markPickedUp(Request $request, $id)
    {
        $delivery = Delivery::where('id', $id)->where('rider_id', $request->user()->id)->firstOrFail();
        
        if ($delivery->status !== 'rider_accepted') {
             return response()->json(['success' => false, 'message' => 'Invalid status transition'], 400);
        }

        $delivery->update([
            'status' => 'picked_up', 
            'picked_up_at' => now()
        ]);
        
        $delivery->statusLogs()->create([
            'status' => 'picked_up',
            'created_by_type' => 'rider',
            'created_by_id' => $request->user()->id
        ]);

        return response()->json(['success' => true, 'message' => 'Delivery picked up']);
    }

    public function markDelivered(Request $request, $id)
    {
        $request->validate(['delivery_pin' => 'nullable|string']); // If PIN required
        
        $delivery = Delivery::where('id', $id)->where('rider_id', $request->user()->id)->firstOrFail();
        
        if ($delivery->status !== 'picked_up' && $delivery->status !== 'in_transit' && $delivery->status !== 'arrived') {
             return response()->json(['success' => false, 'message' => 'Invalid status transition'], 400);
        }

        // Validate PIN if enabled
        // if ($delivery->delivery_pin !== $request->delivery_pin) ...

        DB::transaction(function () use ($delivery, $request) {
            $delivery->update([
                'status' => 'delivered', 
                'delivered_at' => now()
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
        });

        return response()->json(['success' => true, 'message' => 'Delivery completed']);
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

        return response()->json(['success' => true, 'data' => $delivery]);
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

        return response()->json(['success' => true, 'message' => 'Started transit']);
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

        return response()->json(['success' => true, 'message' => 'Marked as arrived']);
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

        return response()->json(['success' => true, 'message' => 'Delivery marked as failed']);
    }

    public function show($id)
    {
        $delivery = Delivery::with(['rider', 'statusLogs'])->findOrFail($id);
        // Authorization check...
        return response()->json(['success' => true, 'data' => $delivery]);
    }
}
