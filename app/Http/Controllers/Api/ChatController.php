<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Delivery;
use App\Models\Message;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function getMessages(Request $request, $deliveryId)
    {
        $user = $request->user();
        $delivery = Delivery::findOrFail($deliveryId);

        // Check if user is part of the delivery
        if ($delivery->user_id !== $user->id && $delivery->rider_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $chat = Chat::firstOrCreate(['delivery_id' => $deliveryId]);
        $messages = $chat->messages()->with('sender')->get();

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function sendMessage(Request $request, $deliveryId)
    {
        $request->validate(['message' => 'required|string']);

        $user = $request->user();
        $delivery = Delivery::findOrFail($deliveryId);

        // Check if user is part of the delivery
        if ($delivery->user_id !== $user->id && $delivery->rider_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $chat = Chat::firstOrCreate(['delivery_id' => $deliveryId]);

        if ($chat->status === 'closed') {
            return response()->json(['success' => false, 'message' => 'Chat is closed'], 400);
        }

        $message = $chat->messages()->create([
            'sender_id' => $user->id,
            'sender_type' => get_class($user),
            'message' => $request->message,
        ]);

        // Send notification to the other party
        if ($user instanceof \App\Models\User) {
            if ($delivery->rider) {
                $this->notificationService->sendToRider($delivery->rider, 'New Message', $request->message, [
                    'type' => 'chat',
                    'delivery_id' => $delivery->id,
                ]);
            }
        } else {
            if ($delivery->user) {
                $this->notificationService->sendToUser($delivery->user, 'New Message', $request->message, [
                    'type' => 'chat',
                    'delivery_id' => $delivery->id,
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $message]);
    }
}
