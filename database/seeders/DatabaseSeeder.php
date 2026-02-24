<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            PricingConfigSeeder::class,
            UserSeeder::class,
            // RiderSeeder::class,
            DeliverySeeder::class,
            WithdrawalSeeder::class,
            SystemSettingSeeder::class,
            // SupportTicketSeeder::class,
            DisputeSeeder::class,
        ]);
    }
}
