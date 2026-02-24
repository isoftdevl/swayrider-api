<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    protected $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid admin credentials',
                ], 401);
            }

            if ($admin->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive',
                ], 403);
            }

            // Check if 2FA is enabled
            if ($admin->two_factor_enabled) {
                // Create a temporary token for 2FA verification (5 minute expiry)
                $tempToken = $admin->createToken('temp-2fa-token', ['temp-2fa'], now()->addMinutes(5))->plainTextToken;

                return response()->json([
                    'success' => true,
                    'requires_2fa' => true,
                    'data' => [
                        'temp_token' => $tempToken,
                    ]
                ]);
            }

            // No 2FA required, complete login
            $admin->last_login_at = now();
            $admin->save();

            $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Admin login successful',
                'data' => [
                    'admin' => $admin,
                    'token' => $token,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function verify2FA(Request $request)
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code' => 'required|string',
        ]);

        // Rate limiting
        $key = 'two-factor-verify:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        try {
            // Find admin with temporary token
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->temp_token);
            
            if (!$personalAccessToken || !$personalAccessToken->can('temp-2fa')) {
                RateLimiter::hit($key, 300); // 5 minutes
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired temporary token',
                ], 401);
            }

            $admin = $personalAccessToken->tokenable;

            // Check if the code is a recovery code
            if (str_contains($request->code, '-') && strlen($request->code) >= 8) {
                $result = $this->twoFactorService->verifyRecoveryCode(
                    $admin->two_factor_recovery_codes,
                    $request->code
                );

                if ($result['valid']) {
                    // Update recovery codes
                    if (count($result['remaining_codes']) > 0) {
                        $admin->two_factor_recovery_codes = $this->twoFactorService->encryptRecoveryCodes($result['remaining_codes']);
                        $admin->save();
                    } else {
                        // No recovery codes left, disable 2FA for safety
                        $admin->two_factor_enabled = false;
                        $admin->two_factor_secret = null;
                        $admin->two_factor_recovery_codes = null;
                        $admin->save();
                    }

                    // Delete temp token and create real token
                    $personalAccessToken->delete();
                    $admin->last_login_at = now();
                    $admin->save();

                    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

                    RateLimiter::clear($key);

                    return response()->json([
                        'success' => true,
                        'message' => 'Login successful',
                        'data' => [
                            'admin' => $admin->fresh(),
                            'token' => $token,
                            'recovery_codes_remaining' => count($result['remaining_codes']),
                        ]
                    ]);
                }
            }

            // Verify TOTP code
            $secret = $this->twoFactorService->decryptSecret($admin->two_factor_secret);
            
            if ($this->twoFactorService->verifyCode($secret, $request->code)) {
                // Delete temp token and create real token
                $personalAccessToken->delete();
                $admin->last_login_at = now();
                $admin->save();

                $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

                RateLimiter::clear($key);

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'admin' => $admin->fresh(),
                        'token' => $token,
                    ]
                ]);
            }

            RateLimiter::hit($key, 300); // 5 minutes

            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code',
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '2FA verification error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function enable2FA(Request $request)
    {
        $admin = $request->user();

        if ($admin->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled',
            ], 400);
        }

        // Generate secret
        $secret = $this->twoFactorService->generateSecret();
        
        // Generate QR code URL
        $qrCodeUrl = $this->twoFactorService->getQRCodeUrl(
            'Swayider Admin',
            $admin->email,
            $secret
        );

        // Generate recovery codes
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        // Store encrypted secret and recovery codes (not enabled yet)
        $admin->two_factor_secret = $this->twoFactorService->encryptSecret($secret);
        $admin->two_factor_recovery_codes = $this->twoFactorService->encryptRecoveryCodes($recoveryCodes);
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'Scan the QR code with your authenticator app',
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'recovery_codes' => $recoveryCodes,
            ]
        ]);
    }

    public function confirm2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $admin = $request->user();

        if ($admin->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled',
            ], 400);
        }

        if (!$admin->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Please enable 2FA first',
            ], 400);
        }

        // Verify the code
        $secret = $this->twoFactorService->decryptSecret($admin->two_factor_secret);
        
        if (!$this->twoFactorService->verifyCode($secret, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code',
            ], 401);
        }

        // Enable 2FA
        $admin->two_factor_enabled = true;
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled successfully',
        ]);
    }

    public function disable2FA(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $admin = $request->user();

        if (!Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        $admin->two_factor_enabled = false;
        $admin->two_factor_secret = null;
        $admin->two_factor_recovery_codes = null;
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled successfully',
        ]);
    }

    public function generateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $admin = $request->user();

        if (!$admin->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        if (!Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        // Generate new recovery codes
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $admin->two_factor_recovery_codes = $this->twoFactorService->encryptRecoveryCodes($recoveryCodes);
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'New recovery codes generated successfully',
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin logged out successfully',
        ]);
    }
}

