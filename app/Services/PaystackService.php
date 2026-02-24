<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    protected $secretKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key', env('PAYSTACK_SECRET_KEY'));
        $this->baseUrl = 'https://api.paystack.co';
    }

    public function initializePayment($email, $data)
    {
        // Paystack amount is in kobo (x100)
        $amountKobo = $data['amount'] * 100;
        
        $response = Http::withToken($this->secretKey)->post($this->baseUrl . '/transaction/initialize', [
            'email' => $email,
            'amount' => $amountKobo,
            'callback_url' => $data['callback_url'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return $response->json();
    }

    public function verifyTransaction($reference)
    {
        $response = Http::withToken($this->secretKey)->get($this->baseUrl . '/transaction/verify/' . $reference);

        return $response->json();
    }
    
    public function createTransferRecipient($name, $accountNumber, $bankCode)
    {
        $response = Http::withToken($this->secretKey)->post($this->baseUrl . '/transferrecipient', [
            'type' => 'nuban',
            'name' => $name,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN'
        ]);
        
        return $response->json();
    }

    public function initiateTransfer($amount, $recipientCode, $reason = 'Withdrawal')
    {
        $response = Http::withToken($this->secretKey)->post($this->baseUrl . '/transfer', [
            'source' => 'balance', 
            'amount' => $amount * 100,
            'recipient' => $recipientCode,
            'reason' => $reason
        ]);

        return $response->json();
    }

    /**
     * Charge a saved authorization code (Tokenized payment)
     */
    public function chargeAuthorization($email, $amount, $authorizationCode, $metadata = [])
    {
        $response = Http::withToken($this->secretKey)->post($this->baseUrl . '/transaction/charge_authorization', [
            'email' => $email,
            'amount' => $amount * 100,
            'authorization_code' => $authorizationCode,
            'metadata' => $metadata,
        ]);

        return $response->json();
    }

    /**
     * Verify Paystack Webhook Signature
     */
    public function isValidWebhookSignature($signature, $payload)
    {
        return $signature === hash_hmac('sha512', $payload, $this->secretKey);
    }
}
