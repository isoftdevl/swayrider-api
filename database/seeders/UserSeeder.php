<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str; 

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // specific user for testing
        $testUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'John Doe',
                'phone' => '08012345678',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        // Create wallet for test user
        Wallet::create([
            'owner_type' => User::class,
            'owner_id' => $testUser->id,
            'balance' => 5000.00,
        ]);

        // Create random users
        User::factory(20)->create()->each(function ($user) {
            Wallet::create([
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'balance' => rand(1000, 50000),
            ]);
        });
    }
}
