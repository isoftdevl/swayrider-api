<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RiderRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:riders',
            'phone' => 'required|string|max:20|unique:riders',
            'password' => 'required|string|min:6|confirmed',
            'bike_registration_number' => 'nullable|string', // Optional at reg, required for KYC
            'emergency_contact' => 'nullable|string',
        ];
    }
}
