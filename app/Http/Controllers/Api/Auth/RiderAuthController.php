<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Mail\RiderVerificationMail;
use App\Mail\NewDeviceLoginMail;
use App\Models\RiderDeviceLog;
use App\Models\Referral;
use App\Models\User;

class RiderAuthController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email',
            'phone' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
            'referral_code' => 'nullable|string',
            // 'bike_registration_number' => 'nullable|unique:riders', // Can happen later in KYC
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        // Check for existing users
        $existingRider = Rider::where('email', $request->email)
            ->orWhere('phone', $request->phone)
            ->first();

        $code = mt_rand(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        if ($existingRider) {
            // If rider exists but is PENDING (unverified), allow re-registration
            if ($existingRider->status === 'pending') {
                // Update existing record with new details and code
                $existingRider->update([
                    'name' => $request->name,
                    'password' => Hash::make($request->password), // Update password just in case
                    'email_verification_code' => $code,
                    'email_verification_expires_at' => $expiresAt,
                    // 'phone' => $request->phone, // Phone might be the matching field
                    // 'email' => $request->email, // Email might be the matching field
                ]);
                
                $rider = $existingRider; // Use the existing rider object
            } else {
                // Rider exists and is NOT pending (Active, Suspended, etc.)
                // Return specific error
                if ($existingRider->email === $request->email) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email address is already registered and verified. Please login.',
                        'field' => 'email'
                    ], 422);
                }

                if ($existingRider->phone === $request->phone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This phone number is already registered and verified. Please login.',
                        'field' => 'phone'
                    ], 422);
                }
            }
        } else {
            // New rider - create fresh record
            $rider = Rider::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'status' => 'pending', 
                'email_verification_code' => $code,
                'email_verification_expires_at' => $expiresAt,
                'referral_code' => Rider::generateReferralCode(),
                'referred_by' => $request->referral_code,
            ]);

            // If referred by someone, create referral record (Only for new riders)
            if ($request->referral_code) {
                $referrer = Rider::where('referral_code', $request->referral_code)->first()
                            ?? User::where('referral_code', $request->referral_code)->first();
                            
                if ($referrer) {
                    Referral::create([
                        'referrer_type' => get_class($referrer),
                        'referrer_id' => $referrer->id,
                        'referred_type' => get_class($rider),
                        'referred_id' => $rider->id,
                        'referral_code' => $request->referral_code,
                        'reward_amount' => 1000.00, // ₦1,000 for rider referrals
                        'reward_claimed' => false,
                        'condition_met' => false,
                    ]);

                    $rider->update(['referred_by' => $referrer->id]);
                }
            }
        }

        try {
            Mail::to($rider->email)->send(new RiderVerificationMail($rider->name, $code));
        } catch (\Exception $e) {
            // Log error but allow registration to proceed
            \Log::error('Failed to send verification email: ' . $e->getMessage());
        }

        $this->walletService->getWallet('App\Models\Rider', $rider->id);

        $token = $rider->createToken('rider_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Rider registered. Please complete KYC.',
            'data' => [
                'rider' => $rider,
                'token' => $token
            ]
        ], 201);
    }
    
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:riders,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Email not found'], 404);
        }

        $rider = Rider::where('email', $request->email)->first();
        
        $code = mt_rand(100000, 999999);
        $rider->update([
            'email_verification_code' => $code,
            'email_verification_expires_at' => now()->addMinutes(15)
        ]);

        try {
            Mail::to($rider->email)->send(new \App\Mail\ForgotPasswordMail($rider->name, $code));
        } catch (\Exception $e) {
            // \Log::error('Failed to send password reset email: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send email. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent to your email'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:riders,email',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rider = Rider::where('email', $request->email)->first();

        if ($rider->email_verification_code !== $request->code) {
            return response()->json(['success' => false, 'message' => 'Invalid reset code'], 400);
        }

        if (now()->greaterThan($rider->email_verification_expires_at)) {
            return response()->json(['success' => false, 'message' => 'Reset code has expired'], 400);
        }

        $rider->update([
            'password' => Hash::make($request->password),
            'email_verification_code' => null,
            'email_verification_expires_at' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful. You can now login.'
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required',
            'password' => 'required',
            'fcm_token' => 'nullable|string'
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $rider = Rider::where($loginType, $request->login)->first();

        if (!$rider || !Hash::check($request->password, $rider->password)) {
             return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if (!$rider->email_verified_at) {
             // Generate new code if missing or expired
             if (!$rider->email_verification_code || ($rider->email_verification_expires_at && now()->greaterThan($rider->email_verification_expires_at))) {
                 $code = mt_rand(100000, 999999);
                 $rider->update([
                     'email_verification_code' => $code,
                     'email_verification_expires_at' => now()->addMinutes(15)
                 ]);
                 try {
                     Mail::to($rider->email)->send(new RiderVerificationMail($rider->name, $code));
                 } catch (\Exception $e) {
                     // \Log::error('Failed to send verification email on login: ' . $e->getMessage());
                 }
             }
             
             return response()->json([
                 'success' => false,
                 'message' => 'Email verification required',
                 'requires_verification' => true,
                 'email' => $rider->email
             ], 403);
        }
        
        if ($request->fcm_token) {
            $rider->update(['fcm_token' => $request->fcm_token]);
        }
        
        // Device Login Detection
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $deviceName = $request->device_name ?? 'Unknown Device'; // Helper from frontend
        
        // Fetch Location from IP
        $location = 'Unknown';
        $latitude = null;
        $longitude = null;

        try {
            $response = Http::get("http://ip-api.com/json/{$ip}");
            if ($response->successful() && $response->json('status') === 'success') {
                $data = $response->json();
                $location = $data['city'] . ', ' . $data['country'];
                $latitude = $data['lat'];
                $longitude = $data['lon'];
            }
        } catch (\Exception $e) {
            // Ignore location errors
        }
        
        $deviceLog = RiderDeviceLog::where('rider_id', $rider->id)
            ->where('device_name', $deviceName)
            ->where('ip_address', $ip)
            ->first();

        if (!$deviceLog) {
            // New device detected
            $loginData = [
                'name' => $rider->name,
                'device_name' => $deviceName,
                'ip_address' => $ip,
                'time' => now()->format('F j, Y, g:i a'),
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];

            try {
                Mail::to($rider->email)->send(new NewDeviceLoginMail($loginData));
            } catch (\Exception $e) {
                // \Log::error('Failed to send device login email: ' . $e->getMessage());
            }

            RiderDeviceLog::create([
                'rider_id' => $rider->id,
                'ip_address' => $ip,
                'device_name' => $deviceName, 
                'user_agent' => $userAgent,
                'last_login_at' => now(),
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        } else {
            $deviceLog->update(['last_login_at' => now()]);
        }

        $token = $rider->createToken('rider_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'rider' => $rider,
                'token' => $token
            ]
        ]);
    }
    public function profile(Request $request)
    {
        $rider = $request->user();
        $rider->load(['wallet', 'profile']);
        
        return response()->json([
            'success' => true,
            'data' => $rider
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

    public function toggleOnline(Request $request)
    {
        $rider = $request->user();
        if (!$rider->is_online && $rider->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Account not active. KYC required.'], 403);
        }
        $rider->is_online = !$rider->is_online;
        $rider->save();
        
        return response()->json(['success' => true, 'is_online' => $rider->is_online]);
    }

    public function goOnline(Request $request)
    {
        $rider = $request->user();
        if ($rider->status !== 'active') {
             return response()->json(['success' => false, 'message' => 'Account not active. KYC required.'], 403);
        }
        $rider->is_online = true;
        $rider->save();
        
        return response()->json(['success' => true, 'is_online' => true]);
    }

    public function goOffline(Request $request)
    {
        $rider = $request->user();
        $rider->is_online = false;
        $rider->save();
        
        return response()->json(['success' => true, 'is_online' => false]);
    }

    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'is_mocked' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rider = $request->user();
        $oldLat = $rider->current_latitude;
        $oldLng = $rider->current_longitude;
        $oldTime = $rider->last_location_update;

        // Perform velocity check for fraud prevention
        if ($oldLat && $oldLng && $oldTime) {
            $distance = $this->calculateDistanceInKm($oldLat, $oldLng, $request->latitude, $request->longitude);
            $timeDiffHours = $oldTime->diffInSeconds(now()) / 3600;

            if ($timeDiffHours > 0) {
                $speed = $distance / $timeDiffHours;
                if ($speed > 150) { // Over 150km/h is suspicious for a bike delivery
                   // \Log::warning("Suspicious speed detected for rider ID: {$rider->id}. Speed: {$speed} km/h");
                    // Optionally: Do not update location or flag the rider
                    // return response()->json(['success' => false, 'message' => 'Suspicious activity detected'], 400);
                }
            }
        }

        if ($request->is_mocked) {
           // \Log::warning("Mock location detected for rider ID: {$rider->id}");
        }

        $rider->update([
            'current_latitude' => $request->latitude,
            'current_longitude' => $request->longitude,
            'last_location_update' => now(),
        ]);

        // Find active delivery for this rider to broadcast to the specific delivery channel
        $activeDelivery = \App\Models\Delivery::where('rider_id', $rider->id)
            ->whereIn('status', ['rider_accepted', 'picked_up', 'in_transit', 'arrived'])
            ->first();

        event(new \App\Events\RiderLocationUpdated(
            $rider->id, 
            $request->latitude, 
            $request->longitude, 
            $activeDelivery ? $activeDelivery->id : null
        ));

        return response()->json(['success' => true, 'message' => 'Location updated']);
    }

    private function calculateDistanceInKm($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function getStats(Request $request)
    {
        $rider = $request->user();
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        // Weekly Earnings
        $weeklyEarnings = $rider->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$startOfWeek, $endOfWeek])
            ->sum('rider_earning');

        // Weekly Deliveries
        $weeklyDeliveries = $rider->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$startOfWeek, $endOfWeek])
            ->count();

        // Completion Rate
        $totalAssigned = $rider->assignedDeliveries()->count();
        $totalDelivered = $rider->assignedDeliveries()->where('status', 'delivered')->count();
        $completionRate = $totalAssigned > 0 ? ($totalDelivered / $totalAssigned) * 100 : 100;

        // Avg per Trip
        $avgPerTrip = $weeklyDeliveries > 0 ? $weeklyEarnings / $weeklyDeliveries : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'acceptance_rate' => 98, // Placeholder for now
                'completion_rate' => round($completionRate),
                'weekly_earnings' => (float)$weeklyEarnings,
                'weekly_deliveries' => $weeklyDeliveries,
                'weekly_goal' => 50000, // Default weekly goal
                'avg_per_trip' => round($avgPerTrip, 2),
                'total_deliveries' => $rider->assignedDeliveries()->count(),
                'total_earnings' => $rider->assignedDeliveries()->where('status', 'delivered')->sum('rider_earning'),
            ]
        ]);
    }

    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $rider = Rider::where('phone', $request->phone)->first();
        if (!$rider) return response()->json(['success' => false, 'message' => 'Rider not found'], 404);

        // Mock verification logic (accepting 123456 for now)
        if ($request->code !== '123456') {
            return response()->json(['success' => false, 'message' => 'Invalid verification code'], 400);
        }

        $rider->update(['phone_verified_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully'
        ]);
    }

    public function resendPhoneVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        // Mock logic: Send SMS here
        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to ' . $request->phone
        ]);
    }



    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $rider = Rider::where('email', $request->email)->first();
        if (!$rider) return response()->json(['success' => false, 'message' => 'Rider not found'], 404);

        // Real verification logic
        
        if (strval($rider->email_verification_code) !== strval($request->code)) {
             return response()->json(['success' => false, 'message' => 'Invalid verification code'], 400);
        }

        if ($rider->email_verification_expires_at && now()->greaterThan($rider->email_verification_expires_at)) {
             return response()->json(['success' => false, 'message' => 'Verification code expired'], 400);
        }

        $rider->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null
        ]);

        // Generate token and log user in
        $token = $rider->createToken('rider_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'data' => [
                'rider' => $rider->fresh(),
                'token' => $token
            ]
        ]);
    }

    public function resendEmailVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $rider = Rider::where('email', $request->email)->first();
        if (!$rider) return response()->json(['success' => false, 'message' => 'Rider not found'], 404);

        if ($rider->email_verified_at) {
             return response()->json(['success' => false, 'message' => 'Email already verified'], 400);
        }

        $code = mt_rand(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        $rider->update([
            'email_verification_code' => $code,
            'email_verification_expires_at' => $expiresAt
        ]);

        try {
            Mail::to($rider->email)->send(new RiderVerificationMail($rider->name, $code));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send email'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to ' . $request->email
        ]);
    }
}
