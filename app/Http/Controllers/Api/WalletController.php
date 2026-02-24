<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaystackService; // Ensure this service exists or is created
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    protected $walletService;
    protected $paystackService;

    public function __construct(WalletService $walletService, PaystackService $paystackService)
    {
        $this->walletService = $walletService;
        $this->paystackService = $paystackService;
    }

    public function getBalance(Request $request)
    {
        $wallet = $this->walletService->getWallet(get_class($request->user()), $request->user()->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (float)$wallet->balance,
                'currency' => 'NGN'
            ]
        ]);
    }

    public function fundWallet(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:100']);
        
        $user = $request->user();
        
        // Ensure wallet exists
        $this->walletService->getWallet(get_class($user), $user->id);
        
        // Initialize Paystack
        $paymentData = $this->paystackService->initializePayment($user->email, [
            'amount' => $request->amount,
            'callback_url' => $request->callback_url,
            'metadata' => [
                'user_id' => $user->id,
                'type' => 'wallet_funding'
            ]
        ]);

        if (empty($paymentData['status'])) {
             return response()->json(['success' => false, 'message' => 'Payment initialization failed'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'authorization_url' => $paymentData['data']['authorization_url'],
                'reference' => $paymentData['data']['reference']
            ]
        ]);
    }

    public function verifyPayment(Request $request, $reference = null)
    {
        $reference = $reference ?? $request->reference;
        
        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'Reference is required'], 400);
        }
        
        $verification = $this->paystackService->verifyTransaction($reference);

        if ($verification['status'] && $verification['data']['status'] === 'success') {
             
             $data = $verification['data'];
             $amount = $data['amount'] / 100;
             $user = $request->user();

             // Ensure wallet exists
             $wallet = $this->walletService->getWallet(get_class($user), $user->id);

             // Credit Wallet
             $this->walletService->credit(
                 $wallet, 
                 $amount, 
                 'funding', 
                 'Wallet Funding via Paystack', 
                 ['paystack_reference' => $reference],
                 'completed'
             );

             // Save Card Token (Authorization)
             if (isset($data['authorization'])) {
                 $auth = $data['authorization'];
                 \App\Models\PaymentMethod::updateOrCreate(
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

             return response()->json(['success' => true, 'message' => 'Wallet funded and card saved successfully']);
        }

        return response()->json(['success' => false, 'message' => 'Payment verification failed'], 400);
    }
    
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
        ]);

        $user = $request->user(); 
        $wallet = $user->wallet;

        if (!$wallet || $wallet->balance < $request->amount) {
            return response()->json(['success' => false, 'message' => 'Insufficient funds'], 400);
        }

        // Create Withdrawal Request
        $withdrawal = DB::transaction(function () use ($user, $wallet, $request) {
            // Debit Wallet
             $this->walletService->debit(
                $wallet, 
                $request->amount, 
                'withdrawal', 
                'Withdrawal request to ' . $request->bank_name
            );

            return \App\Models\Withdrawal::create([
                'withdrawable_type' => get_class($user),
                'withdrawable_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name' => $request->account_name,
                'reference' => 'WD-' . Str::random(10),
                'status' => 'pending'
            ]);
        });

        return response()->json([
            'success' => true, 
            'message' => 'Withdrawal request submitted',
            'data' => $withdrawal
        ]);
    }

    public function getEarnings(Request $request)
    {
        $user = $request->user();
        
        // Earnings stats are primarily for riders
        if (!$user instanceof \App\Models\Rider) {
            return response()->json([
                'success' => true,
                'data' => [
                    'today' => 0,
                    'this_week' => 0,
                    'this_month' => 0,
                    'all_time' => 0,
                    'deliveries_today' => 0,
                    'deliveries_this_week' => 0,
                    'deliveries_this_month' => 0,
                ]
            ]);
        }

        $now = \Carbon\Carbon::now();
        
        $today = $user->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereDate('delivered_at', $now->toDateString())
            ->sum('rider_earning');

        $thisWeek = $user->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$now->startOfWeek()->toDateTimeString(), $now->endOfWeek()->toDateTimeString()])
            ->sum('rider_earning');

        $thisMonth = $user->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereMonth('delivered_at', $now->month)
            ->whereYear('delivered_at', $now->year)
            ->sum('rider_earning');

        $allTime = $user->assignedDeliveries()
            ->where('status', 'delivered')
            ->sum('rider_earning');

        $deliveriesToday = $user->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereDate('delivered_at', $now->toDateString())
            ->count();

        $deliveriesThisWeek = $user->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$now->startOfWeek()->toDateTimeString(), $now->endOfWeek()->toDateTimeString()])
            ->count();

        $deliveriesThisMonth = $user->assignedDeliveries()
            ->where('status', 'delivered')
            ->whereMonth('delivered_at', $now->month)
            ->whereYear('delivered_at', $now->year)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => (float)$today,
                'this_week' => (float)$thisWeek,
                'this_month' => (float)$thisMonth,
                'all_time' => (float)$allTime,
                'deliveries_today' => $deliveriesToday,
                'deliveries_this_week' => $deliveriesThisWeek,
                'deliveries_this_month' => $deliveriesThisMonth,
            ]
        ]);
    }

    public function withdrawals(Request $request)
    {
        $user = $request->user();
        $withdrawals = \App\Models\Withdrawal::where('withdrawable_type', get_class($user))
            ->where('withdrawable_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $withdrawals
        ]);
    }

    public function history(Request $request)
    {
        $wallet = $request->user()->wallet;
        if (!$wallet) return response()->json(['success' => true, 'data' => []]);

        $transactions = $wallet->transactions()->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => $transactions]);
    }

    /**
     * Bolt-style: Charge a saved card
     */
    public function chargeSavedCard(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:100',
        ]);

        $user = $request->user();
        $paymentMethod = \App\Models\PaymentMethod::where('user_id', $user->id)
            ->where('id', $request->payment_method_id)
            ->firstOrFail();

        $response = $this->paystackService->chargeAuthorization(
            $user->email,
            $request->amount,
            $paymentMethod->paystack_authorization_code,
            ['user_id' => $user->id, 'type' => 'recurring_funding']
        );

        if ($response['status'] && $response['data']['status'] === 'success') {
            $amount = $response['data']['amount'] / 100;
            
            $this->walletService->credit(
                $user->wallet,
                $amount,
                'funding',
                'Wallet Funding (Recurring)',
                ['reference' => $response['data']['reference']]
            );

            return response()->json(['success' => true, 'message' => 'Wallet charged successfully']);
        }

        return response()->json([
            'success' => false, 
            'message' => 'Charge failed', 
            'data' => $response
        ], 400);
    }

    public function getSavedCards(Request $request)
    {
        $cards = \App\Models\PaymentMethod::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->get();

        return response()->json(['success' => true, 'data' => $cards]);
    }
}
