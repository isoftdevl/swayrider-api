<?php

namespace App\Http\Requests\Api\Rider;

use Illuminate\Foundation\Http\FormRequest;

class KycSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_type' => 'required|in:national_id,drivers_license,voters_card,passport',
            'id_number' => 'required|string',
            'id_front_photo' => 'required|string', // Base64 or URL if already uploaded? User prompt says "base64 or S3 URL"
            'id_back_photo' => 'required|string',
            'selfie_photo' => 'required|string',
            'bike_registration_number' => 'required|string',
            'bike_photo' => 'required|string',
            'emergency_contact_name' => 'required|string',
            'emergency_contact_phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
        ];
    }
}
