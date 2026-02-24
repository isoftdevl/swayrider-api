<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PromoCodeController extends Controller
{
    /**
     * Validate a promo code for a delivery
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'order_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $code = strtoupper($request->code);
        $orderAmount = $request->order_amount;

        // Find promo code
        $promoCode = PromoCode::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid promo code'
            ], 404);
        }

        // Check if expired
        if ($promoCode->expires_at && Carbon::parse($promoCode->expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'This promo code has expired'
            ], 400);
        }

        // Check if not yet started
        if ($promoCode->starts_at && Carbon::parse($promoCode->starts_at)->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'This promo code is not yet active'
            ], 400);
        }

        // Check usage limit
        if ($promoCode->usage_limit && $promoCode->times_used >= $promoCode->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'This promo code has reached its usage limit'
            ], 400);
        }

        // Check user-specific usage limit
        $userUsageCount = PromoCodeUsage::where('promo_code_id', $promoCode->id)
            ->where('user_id', $user->id)
            ->count();

        if ($userUsageCount >= $promoCode->usage_limit_per_user) {
            return response()->json([
                'success' => false,
                'message' => 'You have already used this promo code the maximum number of times'
            ], 400);
        }

        // Check minimum order amount
        if ($promoCode->min_order_amount && $orderAmount < $promoCode->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => "Minimum order amount of ₦{$promoCode->min_order_amount} required"
            ], 400);
        }

        // Calculate discount
        $discountAmount = 0;
        if ($promoCode->type === 'percentage') {
            $discountAmount = ($orderAmount * $promoCode->value) / 100;
            if ($promoCode->max_discount && $discountAmount > $promoCode->max_discount) {
                $discountAmount = $promoCode->max_discount;
            }
        } else {
            $discountAmount = $promoCode->value;
        }

        // Ensure discount doesn't exceed order amount
        $discountAmount = min($discountAmount, $orderAmount);

        return response()->json([
            'success' => true,
            'message' => 'Promo code is valid',
            'data' => [
                'promo_code' => [
                    'id' => $promoCode->id,
                    'code' => $promoCode->code,
                    'description' => $promoCode->description,
                    'type' => $promoCode->type,
                    'value' => $promoCode->value,
                ],
                'discount_amount' => round($discountAmount, 2),
                'final_amount' => round($orderAmount - $discountAmount, 2),
            ]
        ]);
    }

    /**
     * Get available promo codes for the user
     */
    public function getAvailable(Request $request)
    {
        $user = $request->user();

        $promoCodes = PromoCode::where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function($query) {
                $query->whereNull('usage_limit')
                    ->orWhereRaw('times_used < usage_limit');
            })
            ->get();

        // Filter out codes user has exhausted
        $availableCodes = $promoCodes->filter(function($code) use ($user) {
            $userUsageCount = PromoCodeUsage::where('promo_code_id', $code->id)
                ->where('user_id', $user->id)
                ->count();
            
            return $userUsageCount < $code->usage_limit_per_user;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $availableCodes
        ]);
    }
}
