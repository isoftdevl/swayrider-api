<?php

namespace Database\Seeders;

use App\Models\Rider;
use App\Models\RiderProfile;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RiderSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * ==========================
         * Test Rider
         * ==========================
         */
        $testRider = Rider::create([
            'name' => 'Mike Ross',
            'email' => 'rider@example.com',
            'phone' => '08098765432',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_online' => true,
            'rating' => 4.8,
            'total_deliveries' => 150,
            'total_earnings' => 500000.00,
            'current_latitude' => 6.5244,
            'current_longitude' => 3.3792,
        ]);

        // Attach profile via factory
        $testRider->profile()->create(
            RiderProfile::factory()->make()->toArray()
        );

        // Wallet
        Wallet::create([
            'owner_type' => Rider::class,
            'owner_id' => $testRider->id,
            'balance' => 25000.00,
        ]);

        /**
         * ==========================
         * Random Riders
         * ==========================
         */
        Rider::factory(10)->create()->each(function ($rider) {

            // Rider Profile
            $rider->profile()->create(
                RiderProfile::factory()->make()->toArray()
            );

            // Wallet
            Wallet::create([
                'owner_type' => Rider::class,
                'owner_id' => $rider->id,
                'balance' => rand(1000, 20000),
            ]);
        });
    }
}
