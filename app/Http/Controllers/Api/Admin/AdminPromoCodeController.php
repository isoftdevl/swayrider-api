<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminPromoCodeController extends Controller
{
    /**
     * Get all promo codes with pagination and filters
     */
    public function index(Request $request)
    {
        $query = PromoCode::query()->with('creator:id,name');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'active') {
                $query->where('is_active', true)
                      ->where(function($q) {
                          $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                      });
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<=', now());
            }
        }

        // Type filter
        if ($request->has('type') && in_array($request->type, ['percentage', 'fixed_amount'])) {
            $query->where('type', $request->type);
        }

        // Sort by creation date (newest first)
        $query->orderBy('created_at', 'desc');

        $promoCodes = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $promoCodes->items(),
            'meta' => [
                'current_page' => $promoCodes->currentPage(),
                'last_page' => $promoCodes->lastPage(),
                'per_page' => $promoCodes->perPage(),
                'total' => $promoCodes->total(),
            ]
        ]);
    }

    /**
     * Create a new promo code
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:promo_codes,code|min:4|max:20|regex:/^[A-Z0-9\-]+$/',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:percentage,fixed_amount',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'required|integer|min:1',
            'user_type' => 'nullable|in:all,new,existing',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Additional validation for percentage type
        if ($request->type === 'percentage' && $request->value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage value cannot exceed 100'
            ], 422);
        }

        $data = $request->all();
        $data['code'] = strtoupper($request->code);
        $data['created_by'] = auth()->id();
        $data['times_used'] = 0;

        $promoCode = PromoCode::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Promo code created successfully',
            'data' => $promoCode
        ], 201);
    }

    /**
     * Get single promo code with usage statistics
     */
    public function show($id)
    {
        $promoCode = PromoCode::with('creator:id,name')->find($id);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        // Get usage statistics
        $usageStats = PromoCodeUsage::where('promo_code_id', $id)
            ->selectRaw('COUNT(*) as total_uses, SUM(discount_amount) as total_discount')
            ->first();

        $promoCode->usage_stats = [
            'total_uses' => $usageStats->total_uses ?? 0,
            'total_discount' => $usageStats->total_discount ?? 0,
            'unique_users' => PromoCodeUsage::where('promo_code_id', $id)->distinct('user_id')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $promoCode
        ]);
    }

    /**
     * Update promo code
     */
    public function update(Request $request, $id)
    {
        $promoCode = PromoCode::find($id);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        // Prevent editing heavily used codes
        if ($promoCode->times_used > 50) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit promo code that has been used more than 50 times. Consider creating a new one instead.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|unique:promo_codes,code,' . $id . '|min:4|max:20|regex:/^[A-Z0-9\-]+$/',
            'description' => 'nullable|string|max:500',
            'type' => 'sometimes|in:percentage,fixed_amount',
            'value' => 'sometimes|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:' . $promoCode->times_used,
            'usage_limit_per_user' => 'sometimes|integer|min:1',
            'user_type' => 'nullable|in:all,new,existing',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Additional validation for percentage type
        if ($request->has('type') && $request->type === 'percentage' && $request->value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage value cannot exceed 100'
            ], 422);
        }

        $data = $request->all();
        if (isset($data['code'])) {
            $data['code'] = strtoupper($request->code);
        }

        $promoCode->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Promo code updated successfully',
            'data' => $promoCode
        ]);
    }

    /**
     * Delete promo code
     */
    public function destroy($id)
    {
        $promoCode = PromoCode::find($id);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        // Soft delete by marking as inactive instead of actual deletion
        // This preserves historical data
        $promoCode->update(['is_active' => false]);
        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promo code deleted successfully'
        ]);
    }

    /**
     * Toggle promo code active status
     */
    public function toggleStatus($id)
    {
        $promoCode = PromoCode::find($id);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        $promoCode->update(['is_active' => !$promoCode->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Promo code status updated successfully',
            'data' => [
                'id' => $promoCode->id,
                'is_active' => $promoCode->is_active
            ]
        ]);
    }
}
