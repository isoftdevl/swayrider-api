<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Delivery;

class DeliveryAvailable implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $delivery;
    public $riderId;

    /**
     * Create a new event instance.
     */
    public function __construct(Delivery $delivery, $riderId)
    {
        $this->delivery = $delivery;
        $this->riderId = $riderId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('rider-notifications.' . $this->riderId),
        ];
    }

    public function broadcastWith()
    {
        return [
            'delivery_id' => $this->delivery->id,
            'tracking_number' => $this->delivery->tracking_number,
            'pickup_address' => $this->delivery->pickup_address,
            'dropoff_address' => $this->delivery->dropoff_address,
            'total_price' => $this->delivery->total_price,
            'distance_km' => $this->delivery->distance_km,
            'package_size' => $this->delivery->package_size,
        ];
    }
}
