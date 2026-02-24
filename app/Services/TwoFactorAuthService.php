<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class TwoFactorAuthService
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate a new 2FA secret key
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Generate QR code URL for the secret
     */
    public function getQRCodeUrl(string $companyName, string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            $companyName,
            $email,
            $secret
        );
    }

    /**
     * Verify a TOTP code against a secret
     */
    public function verifyCode(string $secret, string $code): bool
    {
        try {
            // Allow a window of ±1 periods (30 seconds each) to account for time drift
            return $this->google2fa->verifyKey($secret, $code, 2);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // Generate 8-character alphanumeric code
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    /**
     * Encrypt recovery codes for storage
     */
    public function encryptRecoveryCodes(array $codes): string
    {
        return Crypt::encryptString(json_encode($codes));
    }

    /**
     * Decrypt recovery codes from storage
     */
    public function decryptRecoveryCodes(string $encrypted): array
    {
        try {
            return json_decode(Crypt::decryptString($encrypted), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Verify and consume a recovery code
     */
    public function verifyRecoveryCode(string $encrypted, string $code): array
    {
        $codes = $this->decryptRecoveryCodes($encrypted);
        $code = strtoupper(trim($code));

        if (in_array($code, $codes, true)) {
            // Remove the used code
            $codes = array_values(array_filter($codes, fn($c) => $c !== $code));
            return [
                'valid' => true,
                'remaining_codes' => $codes,
            ];
        }

        return [
            'valid' => false,
            'remaining_codes' => $codes,
        ];
    }

    /**
     * Encrypt a secret for database storage
     */
    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    /**
     * Decrypt a secret from database
     */
    public function decryptSecret(string $encrypted): string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Exception $e) {
            return '';
        }
    }
}
