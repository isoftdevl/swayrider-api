<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_price',
        'price_per_km_first_5',
        'price_per_km_after_5',
        'small_package_fee',
        'medium_package_fee',
        'large_package_fee',
        'rush_hour_multiplier',
        'rush_hour_start_time',
        'rush_hour_end_time',
        'evening_rush_start_time',
        'evening_rush_end_time',
        'night_fee_multiplier',
        'night_start_time',
        'night_end_time',
        'express_multiplier',
        'default_commission_percentage',
        'company_commission_percentage',
        'rider_search_radius_km',
        'max_delivery_distance_km',
        'min_withdrawal_amount',
        'max_withdrawal_amount',
        'is_active',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'price_per_km_first_5' => 'decimal:2',
        'price_per_km_after_5' => 'decimal:2',
        'small_package_fee' => 'decimal:2',
        'medium_package_fee' => 'decimal:2',
        'large_package_fee' => 'decimal:2',
        'rush_hour_multiplier' => 'decimal:2',
        'night_fee_multiplier' => 'decimal:2',
        'express_multiplier' => 'decimal:2',
        'default_commission_percentage' => 'decimal:2',
        'company_commission_percentage' => 'decimal:2',
        'rider_search_radius_km' => 'decimal:2',
        'max_delivery_distance_km' => 'decimal:2',
        'min_withdrawal_amount' => 'decimal:2',
        'max_withdrawal_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];
}
