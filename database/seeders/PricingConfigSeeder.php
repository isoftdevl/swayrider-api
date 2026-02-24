<?php

namespace Database\Seeders;

use App\Models\PricingConfig;
use Illuminate\Database\Seeder;

class PricingConfigSeeder extends Seeder
{
    public function run(): void
    {
        PricingConfig::create([
            'base_price' => 500,
            'price_per_km_first_5' => 100,
            'price_per_km_after_5' => 80,
            'small_package_fee' => 0,
            'medium_package_fee' => 200,
            'large_package_fee' => 500,
            'rush_hour_multiplier' => 1.5,
            'rush_hour_start_time' => '07:00:00',
            'rush_hour_end_time' => '09:00:00',
            'evening_rush_start_time' => '17:00:00',
            'evening_rush_end_time' => '19:00:00',
            'night_fee_multiplier' => 1.3,
            'night_start_time' => '22:00:00',
            'night_end_time' => '06:00:00',
            'express_multiplier' => 1.5,
            'default_commission_percentage' => 20,
            'company_commission_percentage' => 10,
            'rider_search_radius_km' => 5,
            'max_delivery_distance_km' => 100,
            'min_withdrawal_amount' => 1000,
            'max_withdrawal_amount' => 50000,
            'is_active' => true,
            'effective_from' => now(),
        ]);
    }
}
