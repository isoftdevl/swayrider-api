<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SOSAlert;
use Illuminate\Http\Request;

class SOSController extends Controller
{
    public function trigger(Request $request, $deliveryId = null)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'comment' => 'nullable|string',
        ]);

        $rider = $request->user();

        $sos = SOSAlert::create([
            'rider_id' => $rider->id,
            'delivery_id' => $deliveryId,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'comment' => $request->comment,
            'status' => 'active',
        ]);

        // In a real production app, we would broadcast this via Pusher/Socket.io
        // or send push notifications to admins here.

        return response()->json([
            'success' => true,
            'message' => 'SOS alert recorded successfully. Help is on the way.',
            'data' => $sos
        ]);
    }
}
