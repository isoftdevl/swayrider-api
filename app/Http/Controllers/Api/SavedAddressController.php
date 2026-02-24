<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SavedAddressController extends Controller
{
    /**
     * Get all saved addresses for the authenticated user
     */
    public function index(Request $request)
    {
        $addresses = SavedAddress::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }

    /**
     * Store a new saved address
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|in:home,work,other',
            'custom_label' => 'required_if:label,other|string|max:255',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'instructions' => 'nullable|string',
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

        // If this is the first address or marked as default, set it as default
        $isDefault = $request->is_default ?? false;
        $existingCount = SavedAddress::where('user_id', $user->id)->count();
        
        if ($existingCount === 0) {
            $isDefault = true;
        }

        // If setting as default, unset other defaults
        if ($isDefault) {
            SavedAddress::where('user_id', $user->id)
                ->update(['is_default' => false]);
        }

        $address = SavedAddress::create([
            'user_id' => $user->id,
            'label' => $request->label,
            'custom_label' => $request->custom_label,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'instructions' => $request->instructions,
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully',
            'data' => $address
        ], 201);
    }

    /**
     * Update a saved address
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|in:home,work,other',
            'custom_label' => 'required_if:label,other|string|max:255',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'instructions' => 'nullable|string',
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
        $address = SavedAddress::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        // If setting as default, unset other defaults
        if ($request->has('is_default') && $request->is_default) {
            SavedAddress::where('user_id', $user->id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $address->update($request->only([
            'label', 'custom_label', 'address', 'latitude', 'longitude',
            'contact_name', 'contact_phone', 'instructions', 'is_default'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address->fresh()
        ]);
    }

    /**
     * Delete a saved address
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $address = SavedAddress::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // If deleted address was default, set another as default
        if ($wasDefault) {
            $newDefault = SavedAddress::where('user_id', $user->id)->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }
}
