<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Admin;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user/rider.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Notification::query();

        // For non-admins, only show their own notifications
        if (!($user instanceof Admin)) {
            $query->where('notifiable_type', get_class($user))
                  ->where('notifiable_id', $user->id);
        }

        $notifications = $query->latest()->paginate(20);

        // Transform to match frontend types
        $notifications->getCollection()->transform(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message ?? $notification->body,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $query = Notification::query();

        if (!($user instanceof Admin)) {
            $query->where('notifiable_type', get_class($user))
                  ->where('notifiable_id', $user->id);
        }

        $notification = $query->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $query = Notification::whereNull('read_at');

        if (!($user instanceof Admin)) {
            $query->where('notifiable_type', get_class($user))
                  ->where('notifiable_id', $user->id);
        }

        $query->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete a specific notification.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $query = Notification::query();

        if (!($user instanceof Admin)) {
            $query->where('notifiable_type', get_class($user))
                  ->where('notifiable_id', $user->id);
        }

        $notification = $query->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Update FCM token for push notifications.
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string'
        ]);

        $user = $request->user();
        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully'
        ]);
    }
}
