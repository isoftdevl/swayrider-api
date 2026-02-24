<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\KycSubmissionRequest;
use App\Models\RiderProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycController extends Controller
{
    public function submit(KycSubmissionRequest $request)
    {
        $rider = $request->user();

        // Check if already submitted
        if ($rider->profile) {
            return response()->json([
                'success' => false,
                'message' => 'KYC already submitted',
            ], 400);
        }

        $data = $request->validated();
        
        // Handle File Uploads (Base64)
        $files = ['id_front_photo', 'id_back_photo', 'selfie_photo', 'bike_photo', 'bike_papers', 'police_clearance'];
        
        foreach ($files as $fileField) {
            if (isset($data[$fileField])) {
                $data[$fileField] = $this->storeBase64File($data[$fileField], "kyc/{$rider->id}");
            }
        }

        $data['rider_id'] = $rider->id;
        $data['verification_status'] = 'pending';

        $profile = RiderProfile::create($data);

        return response()->json([
            'success' => true,
            'message' => 'KYC submitted successfully. Please wait for approval.',
            'data' => $profile
        ]);
    }

    public function show(Request $request)
    {
        $profile = $request->user()->profile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'KYC not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    private function storeBase64File($base64String, $path)
    {
        if (!$base64String) return null;
        
        // Check if it's already a URL (in case of update/resubmission logic not yet impl)
        if (filter_var($base64String, FILTER_VALIDATE_URL)) {
             return $base64String;
        }

        // Decode Base64
        // Format: data:image/png;base64,......
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            $base64String = substr($base64String, strpos($base64String, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, etc.

            if (!in_array($type, ['jpg', 'jpeg', 'png', 'pdf'])) {
                // Default or throw
                $type = 'png';
            }
            
            $base64String = base64_decode($base64String);

            if ($base64String === false) {
                 return null;
            }
        } else {
             return null; // Invalid format
        }

        $filename = Str::random(20) . '.' . $type;
        $fullPath = $path . '/' . $filename;
        
        Storage::disk('public')->put($fullPath, $base64String);

        return Storage::url($fullPath);
    }
}
