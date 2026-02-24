<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderProfile;
use App\Models\Rider;
use App\Mail\KYCSubmittedMail;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class RiderProfileController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Get rider profile data.
     */
    public function getProfile(Request $request)
    {
        $rider = $request->user()->load('profile');
        return response()->json([
            'success' => true,
            'data' => $rider
        ]);
    }

    /**
     * Upload/update rider profile photo.
     */
    public function uploadProfilePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|string', // Base64 or image file logic handled in service
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rider = $request->user();
        $path = "riders/{$rider->id}/profile";
        
        try {
            // Delete old photo if exists
            if ($rider->profile_photo) {
                $this->imageService->delete($rider->profile_photo);
            }

            $photoUrl = $this->imageService->upload($request->photo, $path, 300, 300);
            $rider->update(['profile_photo' => $photoUrl]);

            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully',
                'photo_url' => $photoUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit KYC documents for verification.
     */
    public function submitKYC(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_type' => 'required|string|in:national_id,drivers_license,voters_card,international_passport',
            'id_number' => 'required|string',
            'id_front_photo' => 'required|string',
            'id_back_photo' => 'required|string',
            'selfie_photo' => 'required|string',
            'bike_registration_number' => 'required|string',
            'bike_photo' => 'required|string',
            'bike_papers' => 'nullable|string',
            'police_clearance' => 'nullable|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'emergency_contact_name' => 'required|string',
            'emergency_contact_phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rider = $request->user();
        $basePath = "riders/{$rider->id}/documents";

        try {
            $data = $request->only([
                'id_type', 'id_number', 'address', 'city', 'state', 
                'emergency_contact_name', 'emergency_contact_phone',
                'bike_registration_number'
            ]);

            // Track fields and their upload paths
            $fileFields = [
                'id_front_photo' => $basePath,
                'id_back_photo' => $basePath,
                'selfie_photo' => $basePath,
                'bike_photo' => "riders/{$rider->id}/bike",
                'bike_papers' => "riders/{$rider->id}/bike",
                'police_clearance' => $basePath,
            ];

            foreach ($fileFields as $field => $path) {
                if ($request->has($field) && $request->input($field)) {
                    $data[$field] = $this->imageService->upload($request->input($field), $path);
                }
            }

            $profile = RiderProfile::updateOrCreate(
                ['rider_id' => $rider->id],
                array_merge($data, ['verification_status' => 'pending'])
            );

            // Also update rider table for redundancy/quick access
            $rider->update([
                'bike_registration_number' => $request->bike_registration_number,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_phone' => $request->emergency_contact_phone,
                'kyc_status' => 'pending'
            ]);

            // Send submission email
            try {
                Mail::to($rider->email)->send(new KYCSubmittedMail($rider->name));
            } catch (\Exception $e) {
                \Log::error('Failed to send KYC submission email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'KYC documents submitted successfully. Your account is now under review.',
                'data' => $profile
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit KYC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KYC verification status.
     */
    public function getKYCStatus(Request $request)
    {
        $rider = $request->user();
        $profile = $rider->profile;

        return response()->json([
            'success' => true,
            'status' => $profile ? $profile->verification_status : 'not_submitted',
            'rejection_reason' => $profile ? $profile->rejection_reason : null,
            'verified_at' => $profile ? $profile->verified_at : null,
        ]);
    }
}
