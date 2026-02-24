<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $riders = Rider::where('status', 'active')->get();

        if ($users->isEmpty() || $riders->isEmpty()) {
            $this->command->warn('No users or riders found. Skipping delivery seeding.');
            return;
        }

        // Create 30 random deliveries
        for ($i = 0; $i < 30; $i++) {
            $user = $users->random();
            $status = collect(['pending', 'assigned', 'rider_accepted', 'picked_up', 'in_transit', 'delivered', 'cancelled'])->random();
            $rider = ($status !== 'pending' && $status !== 'cancelled') ? $riders->random() : null;
            
            $distance = rand(5, 50) / 10; // 0.5 to 5.0 km
            $basePrice = 500;
            $distancePrice = $distance * 200;
            $totalPrice = $basePrice + $distancePrice;
            $platformCommission = $totalPrice * 0.15;
            $riderEarning = $totalPrice - $platformCommission;

            Delivery::create([
                'user_id' => $user->id,
                'rider_id' => $rider ? $rider->id : null,
                'tracking_number' => 'SR-' . strtoupper(Str::random(10)),
                
                'pickup_address' => fake()->streetAddress() . ', Lagos',
                'pickup_latitude' => 6.5244 + (rand(-100, 100) / 1000),
                'pickup_longitude' => 3.3792 + (rand(-100, 100) / 1000),
                'pickup_contact_name' => fake()->name(),
                'pickup_contact_phone' => '080' . rand(10000000, 99999999),
                
                'dropoff_address' => fake()->streetAddress() . ', Lagos',
                'dropoff_latitude' => 6.6000 + (rand(-100, 100) / 1000),
                'dropoff_longitude' => 3.3500 + (rand(-100, 100) / 1000),
                'dropoff_contact_name' => fake()->name(),
                'dropoff_contact_phone' => '080' . rand(10000000, 99999999),
                
                'package_size' => collect(['small', 'medium', 'large'])->random(),
                'package_description' => 'Test Package ' . ($i + 1),
                
                'distance_km' => $distance,
                'base_price' => $basePrice,
                'distance_price' => $distancePrice,
                'total_price' => $totalPrice,
                'platform_commission' => $platformCommission,
                'rider_earning' => $riderEarning,
                
                'status' => $status,
                'urgency' => collect(['normal', 'express'])->random(),
                'payment_method' => collect(['wallet', 'card', 'cash'])->random(),
                'payment_status' => $status === 'delivered' ? 'paid' : collect(['pending', 'paid'])->random(),
                
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now()->subDays(rand(0, 30)),
            ]);
        }
        
        $this->command->info('Created 30 test deliveries');
    }
}
