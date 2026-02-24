<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RiderBankAccountController extends Controller
{
    /**
     * Get authenticated rider's saved bank accounts.
     */
    public function index(Request $request)
    {
        $rider = $request->user();

        $accounts = BankAccount::where('user_id', $rider->id)
            ->where('user_type', 'rider')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($accounts);
    }

    /**
     * Save a new bank account.
     */
    public function store(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string',
            'bank_code' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
        ]);

        $rider = $request->user();

        // Check if account already exists for this user to avoid duplicates
        $exists = BankAccount::where('user_id', $rider->id)
            ->where('user_type', 'rider')
            ->where('account_number', $request->account_number)
            ->where('bank_code', $request->bank_code)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Account already saved'], 409);
        }

        // Create account
        $account = BankAccount::create([
            'user_id' => $rider->id,
            'user_type' => 'rider',
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'is_primary' => false, // Default to false
        ]);

        return response()->json($account, 201);
    }

    /**
     * Delete a saved bank account.
     */
    public function destroy(Request $request, $id)
    {
        $rider = $request->user();

        $account = BankAccount::where('id', $id)
            ->where('user_id', $rider->id)
            ->where('user_type', 'rider')
            ->first();

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /**
     * Resolve account name via Paystack (or mock).
     */
    public function resolveAccount(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string',
            'bank_code' => 'required|string',
        ]);

        $accountNumber = $request->account_number;
        $bankCode = $request->bank_code;
        $secretKey = env('PAYSTACK_SECRET_KEY');

        // MOCK MODE if no key or specific env var
        if (env('APP_ENV') === 'local' && !$secretKey) {
            // Simple mock logic
            return response()->json([
                'account_number' => $accountNumber,
                'account_name' => 'MOCK USER NAME',
                'bank_id' => 1
            ]);
        }

        try {
            // Log request for debugging
            // Log::info("Resolving account: $accountNumber with code $bankCode");

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Cache-Control' => 'no-cache',
            ])->get("https://api.paystack.co/bank/resolve", [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

            if ($response->successful()) {
                return response()->json($response->json()['data']);
            }

            // Log the error response
            // Log::error('Paystack Resolve Failed: ' . $response->body());
            
            $errorMessage = $response->json()['message'] ?? 'Could not resolve account details';
            return response()->json(['message' => $errorMessage], 422);

        } catch (\Exception $e) {
            Log::error('Paystack Resolve Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Service unavailable: ' . $e->getMessage()], 503);
        }
    }
}
