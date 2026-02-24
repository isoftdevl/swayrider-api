<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Get all payment methods for the authenticated user
     */
    public function index(Request $request)
    {
        $paymentMethods = PaymentMethod::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }

    /**
     * Store a new payment method (from Paystack authorization)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'authorization_code' => 'required|string',
            'card_type' => 'required|string',
            'last4' => 'required|string|size:4',
            'exp_month' => 'required|string|size:2',
            'exp_year' => 'required|string|size:4',
            'bank' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if authorization code already exists
        $existing = PaymentMethod::where('paystack_authorization_code', $request->authorization_code)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This card is already saved'
            ], 400);
        }

        // If this is the first card or marked as default, set it as default
        $isDefault = $request->is_default ?? false;
        $existingCount = PaymentMethod::where('user_id', $user->id)->count();
        
        if ($existingCount === 0) {
            $isDefault = true;
        }

        // If setting as default, unset other defaults
        if ($isDefault) {
            PaymentMethod::where('user_id', $user->id)
                ->update(['is_default' => false]);
        }

        $paymentMethod = PaymentMethod::create([
            'user_id' => $user->id,
            'paystack_authorization_code' => $request->authorization_code,
            'card_type' => $request->card_type,
            'last4' => $request->last4,
            'exp_month' => $request->exp_month,
            'exp_year' => $request->exp_year,
            'bank' => $request->bank,
            'is_default' => $isDefault,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment method saved successfully',
            'data' => $paymentMethod
        ], 201);
    }

    /**
     * Set a payment method as default
     */
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();

        $paymentMethod = PaymentMethod::where('id', $id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        // Unset all other defaults
        PaymentMethod::where('user_id', $user->id)
            ->where('id', '!=', $id)
            ->update(['is_default' => false]);

        $paymentMethod->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default payment method updated',
            'data' => $paymentMethod->fresh()
        ]);
    }

    /**
     * Delete a payment method
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $paymentMethod = PaymentMethod::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        // If deleted card was default, set another as default
        if ($wasDefault) {
            $newDefault = PaymentMethod::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully'
        ]);
    }
}
