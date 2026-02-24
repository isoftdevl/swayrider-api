<?php

namespace Database\Seeders;

use App\Models\Rider;
use App\Models\Withdrawal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WithdrawalSeeder extends Seeder
{
    public function run(): void
    {
        $riders = Rider::whereHas('wallet')->with('wallet')->get();
        
        if ($riders->isEmpty()) {
            $this->command->warn('No riders with wallets found. Skipping withdrawal seeding.');
            return;
        }

        // Create 15 random withdrawals
        for ($i = 0; $i < 15; $i++) {
            $rider = $riders->random();
            $amount = rand(1000, 10000);
            $status = collect(['pending', 'completed', 'rejected'])->random();
            
            Withdrawal::create([
                'withdrawable_type' => 'App\Models\Rider',
                'withdrawable_id' => $rider->id,
                'wallet_id' => $rider->wallet->id,
                'amount' => $amount,
                'bank_name' => collect(['Access Bank', 'GTBank', 'First Bank', 'UBA', 'Zenith Bank'])->random(),
                'account_number' => '01' . rand(10000000, 99999999),
                'account_name' => $rider->name,
                'reference' => 'WD-' . strtoupper(Str::random(10)),
                'status' => $status,
                'processed_at' => $status !== 'pending' ? now()->subDays(rand(0, 10)) : null,
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }
        
        $this->command->info('Created 15 test withdrawals');
    }
}
