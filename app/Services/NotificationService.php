<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Rider;
use Illuminate\Support\Facades\Http;

class NotificationService
{
    public function sendToUser(User $user, $title, $message, $data = [], $type = 'general')
    {
        // Save to DB
        $user->notifications()->create([
            'type' => $type,
            'title' => $title,
            'body' => $message,
            'data' => $data,
        ]);

        // Send Push (FCM)
        if ($user->fcm_token) {
            $this->sendFCM($user->fcm_token, $title, $message, $data);
        }
    }

    public function sendToRider(Rider $rider, $title, $message, $data = [], $type = 'general')
    {
        $rider->notifications()->create([
            'type' => $type,
            'title' => $title,
            'body' => $message,
            'data' => $data,
        ]);

        if ($rider->fcm_token) {
            $this->sendFCM($rider->fcm_token, $title, $message, $data);
        }
    }

    private function sendFCM($token, $title, $message, $data)
    {
        // Mock implementation or use Firebase API
        // Http::post('https://fcm.googleapis.com/fcm/send', ...);
    }
}
