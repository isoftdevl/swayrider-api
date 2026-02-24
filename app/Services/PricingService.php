<?php

namespace App\Services;

use App\Models\PricingConfig;
use Carbon\Carbon;

class PricingService
{
    protected $config;

    public function __construct()
    {
        $this->config = PricingConfig::where('is_active', true)->latest()->first();
        
        if (!$this->config) {
             throw new \Exception("No active pricing configuration found.");
        }
    }

    public function calculateDeliveryPrice($distanceKm, $packageSize, $urgency = 'normal')
    {
        $config = $this->config;
        $basePrice = $config->base_price;
        
        // Distance Calculation
        if ($distanceKm <= 5) {
            $distancePrice = $distanceKm * $config->price_per_km_first_5;
        } else {
            $distancePrice = (5 * $config->price_per_km_first_5) + (($distanceKm - 5) * $config->price_per_km_after_5);
        }

        // Package Size Fee
        $sizeFee = 0;
        switch ($packageSize) {
            case 'medium': $sizeFee = $config->medium_package_fee; break;
            case 'large': $sizeFee = $config->large_package_fee; break;
            default: $sizeFee = $config->small_package_fee; break;
        }

        // Time Based Fees
        $timeFee = 0;
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');

        // Check Rush Hour (Morning)
        if ($this->isTimeInInterval($currentTime, $config->rush_hour_start_time, $config->rush_hour_end_time)) {
             $timeFee += ($basePrice + $distancePrice) * ($config->rush_hour_multiplier - 1);
        }
        // Check Evening Rush
        elseif ($this->isTimeInInterval($currentTime, $config->evening_rush_start_time, $config->evening_rush_end_time)) {
             $timeFee += ($basePrice + $distancePrice) * ($config->rush_hour_multiplier - 1);
        }
        // Check Night
        elseif ($this->isTimeInInterval($currentTime, $config->night_start_time, $config->night_end_time)) {
             $timeFee += ($basePrice + $distancePrice) * ($config->night_fee_multiplier - 1);
        }

        $subtotal = $basePrice + $distancePrice + $sizeFee + $timeFee;

        // Urgency
        $urgencyMultiplier = 1.0;
        if ($urgency === 'express') {
            $urgencyMultiplier = $config->express_multiplier;
            $subtotal *= $urgencyMultiplier;
        }

        $totalPrice = round($subtotal, 2);

        return [
            'distance_km' => $distanceKm,
            'base_price' => $basePrice,
            'distance_price' => round($distancePrice, 2),
            'size_fee' => $sizeFee,
            'time_fee' => round($timeFee, 2),
            'urgency_multiplier' => $urgencyMultiplier,
            'total_price' => $totalPrice,
            'breakdown' => [
                 ['label' => 'Base Price', 'amount' => $basePrice],
                 ['label' => 'Distance Fee', 'amount' => round($distancePrice, 2)],
                 ['label' => 'Package Size Fee', 'amount' => $sizeFee],
                 ['label' => 'Time Surcharge', 'amount' => round($timeFee, 2)],
            ]
        ];
    }
    
    public function calculateCommissions($totalPrice, $rider)
    {
        $config = $this->config;
        
        $commissionRate = $config->default_commission_percentage; // e.g. 20%
        
        $platformCommission = $totalPrice * ($commissionRate / 100);
        $riderEarning = $totalPrice - $platformCommission;
        $companyCommission = 0;

        // If rider belongs to a company
        if ($rider->company_id && $rider->company) {
             // Platform keeps 20%
             // Rider belongs to company, so company gets earnings minus rider's "salary" usually, 
             // BUT prompt says: "Company keeps 10% of rider_earning" from existing logic
             
             // Prompt Logic:
             // Platform keeps: 20% of total
             // Company keeps: 10% of (Rider Earning) or fixed percentage?
             // Re-reading prompt: "Company keeps: 10% of rider_earning" -> "Rider gets: 70% of total" (if plat=20, comp=10 of total?)
             
             // Let's implement Config driven logic using `company_commission_percentage`
             // Assume company_commission_percentage is what company takes from remaining amount?
             
             // Easier logic based on prompt examples:
             // Total: 1000
             // Plat (20%): 200
             // Remaining: 800
             
             // If Company:
             // Company Config (e.g. 10% of TOTAL or of Remaining?)
             // Prompt says: "Company keeps 10% of rider_earning" -> implies 10% of 800 = 80? Or 10% of total?
             // The prompt breakdown: "Rider gets 70% of total" => Plat(20) + Comp(10) + Rider(70) = 100.
             
             $companyRate = $config->company_commission_percentage; // Should be around 10%
             $companyCommission = $totalPrice * ($companyRate / 100);
             
             $riderEarning = $totalPrice - $platformCommission - $companyCommission;
        }

        return [
            'platform_commission' => round($platformCommission, 2),
            'company_commission' => round($companyCommission, 2),
            'rider_earning' => round($riderEarning, 2)
        ];
    }
    
    public function applyPromoCode($price, $code, $userId)
    {
        // ... Todo
        return $price;
    }

    private function isTimeInInterval($time, $start, $end)
    {
        if ($start < $end) {
            return $time >= $start && $time <= $end;
        }
        return $time >= $start || $time <= $end;
    }
}
