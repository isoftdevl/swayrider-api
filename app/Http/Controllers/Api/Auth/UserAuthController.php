<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserVerificationMail;
use App\Mail\PasswordResetMail;
use App\Services\ImageUploadService;

class UserAuthController extends Controller
{
    protected $walletService;
    protected $imageService;

    public function __construct(WalletService $walletService, ImageUploadService $imageService)
    {
        $this->walletService = $walletService;
        $this->imageService = $imageService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users', // Add +234 regex
            'password' => 'required|string|min:8|confirmed',
            'referral_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $code = mt_rand(100000, 999999);
            $expiresAt = now()->addMinutes(15);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'status' => 'active',
                'email_verification_code' => $code,
                'email_verification_expires_at' => $expiresAt,
            ]);

            // Create Wallet
            $this->walletService->getWallet('App\Models\User', $user->id);

            // Handle Referral
            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first() 
                            ?? \App\Models\Rider::where('referral_code', $request->referral_code)->first();

                if ($referrer) {
                    \App\Models\Referral::create([
                        'referrer_type' => get_class($referrer),
                        'referrer_id' => $referrer->id,
                        'referred_type' => 'App\Models\User',
                        'referred_id' => $user->id,
                        'referral_code' => $request->referral_code,
                        'reward_amount' => 500.00, // Fixed referral reward amount
                    ]);

                    $user->update(['referred_by' => $referrer->id]);
                }
            }

            try {
                Mail::to($user->email)->send(new UserVerificationMail($user->name, $code));
            } catch (\Exception $e) {
                \Log::error('Failed to send user verification email: ' . $e->getMessage());
            }

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. Please verify your email.',
            'data' => [
                'user' => $user
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        // Support both 'login' (email/phone) and 'phone' fields from different app versions
        $loginValue = $request->login ?? $request->phone;
        
        $validator = Validator::make($request->all(), [
            'login' => $request->has('phone') ? 'nullable' : 'required_without:phone',
            'phone' => $request->has('login') ? 'nullable' : 'required_without:login',
            'password' => 'required',
            'fcm_token' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $loginType = filter_var($loginValue, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($loginType, $loginValue)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
             return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        
        if ($user->status === 'suspended' || $user->status === 'banned') {
            return response()->json(['success' => false, 'message' => 'Your account has been ' . $user->status], 403);
        }

        if ($request->fcm_token) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        if (!$user->email_verified_at) {
            if (!$user->email_verification_code || ($user->email_verification_expires_at && now()->greaterThan($user->email_verification_expires_at))) {
                $code = mt_rand(100000, 999999);
                $user->update([
                    'email_verification_code' => $code,
                    'email_verification_expires_at' => now()->addMinutes(15)
                ]);
                try {
                    Mail::to($user->email)->send(new UserVerificationMail($user->name, $code));
                } catch (\Exception $e) {
                    \Log::error('Failed to send user verification email on login: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Email verification required',
                'requires_verification' => true,
                'email' => $user->email
            ], 403);
        }

        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Implement actual OTP verification with SMS service
        // For now, accept any 6-digit code for testing
        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->update(['phone_verified_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully',
            'data' => $user
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if (strval($user->email_verification_code) !== strval($request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code'
            ], 400);
        }

        if ($user->email_verification_expires_at && now()->greaterThan($user->email_verification_expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code expired'
            ], 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null
        ]);

        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'data' => [
                'user' => $user->fresh(),
                'token' => $token
            ]
        ]);
    }

    public function resendEmailVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified'
            ], 400);
        }

        $code = mt_rand(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        $user->update([
            'email_verification_code' => $code,
            'email_verification_expires_at' => $expiresAt
        ]);

        try {
            Mail::to($user->email)->send(new UserVerificationMail($user->name, $code));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to ' . $request->email
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'If your email is registered, you will receive a reset code.'
            ], 200); // Security best practice: don't reveal if user exists
        }

        $code = mt_rand(100000, 999999);
        $user->update([
            'password_reset_code' => $code,
            'password_reset_expires_at' => now()->addMinutes(15)
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user->name, $code));
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'If your email is registered, you will receive a reset code.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string|size:6', // We use 'token' as the key from frontend
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request'
            ], 400);
        }

        if (strval($user->password_reset_code) !== strval($request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code'
            ], 400);
        }

        if ($user->password_reset_expires_at && now()->greaterThan($user->password_reset_expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code expired'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_reset_code' => null,
            'password_reset_expires_at' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    public function getProfile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['name', 'email', 'phone']));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()
        ]);
    }

    public function uploadProfilePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $path = "users/{$user->id}/profile";

        try {
            // Delete old photo if exists
            if ($user->profile_photo) {
                $this->imageService->delete($user->profile_photo);
            }

            $photoUrl = $this->imageService->upload($request->photo, $path, 300, 300);
            $user->update(['profile_photo' => $photoUrl]);

            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully',
                'data' => [
                    'user' => $user->fresh(),
                    'profile_photo' => $photoUrl
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Soft delete the user
        $user->delete();

        // Delete all tokens
        $user->tokens()->delete();

        return response()->json([ 
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    }
}
