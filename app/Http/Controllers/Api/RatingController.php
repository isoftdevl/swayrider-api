<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function submitRating(Request $request)
    {
        $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string'
        ]);

        $delivery = Delivery::findOrFail($request->delivery_id);
        
        // Ensure user owns delivery
        if ($delivery->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $rating = Rating::create([
            'delivery_id' => $delivery->id,
            'user_id' => $request->user()->id,
            'rider_id' => $delivery->rider_id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);
        
        // Update rider average rating
        $rider = $delivery->rider;
        $newAvg = $rider->ratings()->avg('rating'); // Assume relation exists
        $rider->update(['rating' => $newAvg]);

        return response()->json(['success' => true, 'data' => $rating]);
    }

    // Get rider's ratings
    public function getRatings(Request $request)
    {
        $rider = $request->user();
        
        $query = Rating::where('rider_id', $rider->id)
            ->with(['user', 'delivery'])
            ->orderBy('created_at', 'desc');

        // Filter by rating if provided
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        $ratings = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $ratings->items(),
            'meta' => [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
            ]
        ]);
    }

    // Get rating statistics
    public function getStats(Request $request)
    {
        $rider = $request->user();
        
        $ratings = Rating::where('rider_id', $rider->id);
        
        $stats = [
            'average_rating' => round($ratings->avg('rating') ?? 0, 2),
            'total_ratings' => $ratings->count(),
            'five_star' => $ratings->where('rating', 5)->count(),
            'four_star' => $ratings->where('rating', 4)->count(),
            'three_star' => $ratings->where('rating', 3)->count(),
            'two_star' => $ratings->where('rating', 2)->count(),
            'one_star' => $ratings->where('rating', 1)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // Respond to a rating
    public function respondToRating(Request $request, $id)
    {
        $request->validate([
            'response' => 'required|string|max:500'
        ]);

        $rider = $request->user();
        $rating = Rating::findOrFail($id);

        // Ensure rating belongs to this rider
        if ($rating->rider_id !== $rider->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $rating->update([
            'response' => $request->response,
            'responded_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'data' => $rating->fresh(['user', 'delivery'])
        ]);
    }
}
