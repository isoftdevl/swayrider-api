<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;

class DeliveryMatchingService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function findNearestRiders($lat, $lng, $radiusKm = 10, $limit = 20)
    {
        // Haversine formula + Priority Scoring
        // Score = distance(60%) + rating(20%) + acceptance_rate(20%)
        // Normalized: Higher score is better
        
        $radiusKm = config('delivery.matching_radius', 10);

        return Rider::select('riders.*')
            ->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(current_latitude)) * cos(radians(current_longitude) - radians(?)) + sin(radians(?)) * sin(radians(current_latitude)))) AS distance", [$lat, $lng, $lat])
            ->where('status', 'active')
            ->where('is_online', true)
            ->whereNull('deleted_at')
            ->having('distance', '<', $radiusKm)
            ->orderByRaw("
                (
                    ((1 - (distance / ?)) * 0.6) + 
                    ((rating / 5) * 0.2) + 
                    (0.2) -- Placeholder for acceptance_rate
                ) DESC
            ", [$radiusKm])
            ->limit($limit)
            ->get();
    }

    public function broadcastToRiders(Delivery $delivery, $riders)
    {
        foreach ($riders as $rider) {
            // 1. Send Push Notification (FCM)
            $this->notificationService->sendToRider(
                $rider,
                'New Delivery Request',
                'New delivery available nearby approx ' . round($rider->distance, 1) . 'km away.',
                ['delivery_id' => $delivery->id],
                'new_request'
            );
            
            // 2. Trigger WebSocket Event
            event(new \App\Events\DeliveryAvailable($delivery, $rider->id));
        }
    }
}
