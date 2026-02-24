<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $riderId;
    public $latitude;
    public $longitude;
    public $deliveryId;

    /**
     * Create a new event instance.
     */
    public function __construct($riderId, $latitude, $longitude, $deliveryId = null)
    {
        $this->riderId = $riderId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->deliveryId = $deliveryId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('rider-location.' . $this->riderId)];

        if ($this->deliveryId) {
            $channels[] = new Channel('delivery.' . $this->deliveryId);
        }

        return $channels;
    }

    public function broadcastWith()
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'rider_id' => $this->riderId,
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
