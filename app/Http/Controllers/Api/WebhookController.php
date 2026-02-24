<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaystackService;
use App\Services\WalletService;
use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $paystackService;
    protected $walletService;

    public function __construct(PaystackService $paystackService, WalletService $walletService)
    {
        $this->paystackService = $paystackService;
        $this->walletService = $walletService;
    }

    public function handlePaystack(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (!$this->paystackService->isValidWebhookSignature($signature, $payload)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        Log::info('Paystack Webhook Received:', ['event' => $event['event']]);

        switch ($event['event']) {
            case 'charge.success':
                $this->handleChargeSuccess($event['data']);
                break;
            
            // Add more cases as needed (e.g., transfer.success, transfer.failed)
        }

        return response(200);
    }

    protected function handleChargeSuccess($data)
    {
        $email = $data['customer']['email'];
        $user = User::where('email', $email)->first();

        if (!$user) {
            Log::warning('User not found for Paystack webhook:', ['email' => $email]);
            return;
        }

        // Logic to prevent duplicate processing
        // ... (Usually check reference in wallet_transactions table)

        $amount = $data['amount'] / 100;
        $reference = $data['reference'];
        $metadata = $data['metadata'] ?? [];

        // Credit Wallet
        $this->walletService->credit(
            $user->wallet,
            $amount,
            'funding',
            'Wallet Funding (Webhook)',
            ['reference' => $reference, 'paystack_data' => $data]
        );

        // Save/Update Payment Method if authorization is present
        if (isset($data['authorization'])) {
            $this->savePaymentMethod($user, $data['authorization']);
        }
    }

    protected function savePaymentMethod(User $user, $auth)
    {
        PaymentMethod::updateOrCreate(
            [
                'user_id' => $user->id,
                'paystack_authorization_code' => $auth['authorization_code'],
            ],
            [
                'card_type' => $auth['card_type'],
                'last4' => $auth['last4'],
                'exp_month' => $auth['exp_month'],
                'exp_year' => $auth['exp_year'],
                'bank' => $auth['bank'],
                'is_active' => true,
            ]
        );
    }
}
