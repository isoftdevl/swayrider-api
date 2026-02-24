<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    public function getWallet($ownerType, $ownerId)
    {
        return Wallet::firstOrCreate(
            ['owner_type' => $ownerType, 'owner_id' => $ownerId],
            ['balance' => 0.00]
        );
    }

    public function credit(Wallet $wallet, float $amount, string $category, string $description, ?array $metadata = null, $status = 'completed')
    {
        return DB::transaction(function () use ($wallet, $amount, $category, $description, $metadata, $status) {
            $balanceBefore = $wallet->balance;
            
            if ($status === 'completed') {
                $wallet->balance += $amount;
                $wallet->total_credited += $amount;
                $wallet->save();
            }

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'reference' => 'TXN-' . strtoupper(Str::random(12)),
                'description' => $description,
                'metadata' => $metadata,
                'status' => $status
            ]);
        });
    }

    public function debit(Wallet $wallet, float $amount, string $category, string $description, ?array $metadata = null)
    {
        return DB::transaction(function () use ($wallet, $amount, $category, $description, $metadata) {
            if ($wallet->balance < $amount) {
                throw new \Exception("Insufficient wallet balance");
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->total_debited += $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'reference' => 'TXN-' . strtoupper(Str::random(12)),
                'description' => $description,
                'metadata' => $metadata,
                'status' => 'completed'
            ]);
        });
    }
}
