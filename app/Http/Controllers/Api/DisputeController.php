<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DisputeController extends Controller
{
    /**
     * Create a new dispute
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_id' => 'required|exists:deliveries,id',
            'category' => 'required|in:non_delivery,damaged_item,wrong_item,wrong_address,rider_behavior,payment_issue,other',
            'description' => 'required|string|min:50',
            'evidence_photos' => 'nullable|array|max:5',
            'evidence_photos.*' => 'string', // URLs or base64
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Verify delivery belongs to user
        $delivery = Delivery::where('id', $request->delivery_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$delivery) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery not found'
            ], 404);
        }

        // Check if dispute already exists for this delivery
        $existingDispute = Dispute::where('delivery_id', $delivery->id)
            ->where('raised_by_type', 'App\\Models\\User')
            ->where('raised_by_id', $user->id)
            ->first();

        if ($existingDispute) {
            return response()->json([
                'success' => false,
                'message' => 'A dispute already exists for this delivery'
            ], 400);
        }

        $dispute = Dispute::create([
            'delivery_id' => $delivery->id,
            'raised_by_type' => 'App\\Models\\User',
            'raised_by_id' => $user->id,
            'category' => $request->category,
            'description' => $request->description,
            'evidence_photos' => $request->evidence_photos,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dispute created successfully',
            'data' => $dispute->load('delivery')
        ], 201);
    }

    /**
     * Get all disputes for the authenticated user
     */
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = Dispute::where('raised_by_type', 'App\\Models\\User')
            ->where('raised_by_id', $request->user()->id)
            ->with('delivery');

        if ($status) {
            $query->where('status', $status);
        }

        $disputes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $disputes
        ]);
    }

    /**
     * Get a specific dispute with messages
     */
    public function show(Request $request, $id)
    {
        $dispute = Dispute::where('id', $id)
            ->where('raised_by_type', 'App\\Models\\User')
            ->where('raised_by_id', $request->user()->id)
            ->with(['delivery', 'messages'])
            ->first();

        if (!$dispute) {
            return response()->json([
                'success' => false,
                'message' => 'Dispute not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $dispute
        ]);
    }

    /**
     * Send a message in a dispute
     */
    public function sendMessage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        $dispute = Dispute::where('id', $id)
            ->where('raised_by_type', 'App\\Models\\User')
            ->where('raised_by_id', $user->id)
            ->first();

        if (!$dispute) {
            return response()->json([
                'success' => false,
                'message' => 'Dispute not found'
            ], 404);
        }

        if (in_array($dispute->status, ['resolved', 'closed', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send messages to a closed dispute'
            ], 400);
        }

        $message = DisputeMessage::create([
            'dispute_id' => $dispute->id,
            'sender_type' => 'App\\Models\\User',
            'sender_id' => $user->id,
            'message' => $request->message,
            'attachments' => $request->attachments,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message
        ], 201);
    }
}
