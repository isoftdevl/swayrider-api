<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'app_name' => 'Swayider',
            'support_email' => 'support@swayrider.com',
            'support_phone' => '+234 800 123 4567',
            'currency' => 'NGN',
            'currency_symbol' => '₦',
            'timezone' => 'Africa/Lagos',
            'min_withdrawal' => '1000',
            'max_withdrawal' => '1000000',
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
