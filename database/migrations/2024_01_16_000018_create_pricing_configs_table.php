<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('base_price', 10, 2);
            $table->decimal('price_per_km_first_5', 10, 2);
            $table->decimal('price_per_km_after_5', 10, 2);
            $table->decimal('small_package_fee', 10, 2)->default(0);
            $table->decimal('medium_package_fee', 10, 2);
            $table->decimal('large_package_fee', 10, 2);
            $table->decimal('rush_hour_multiplier', 3, 2);
            $table->time('rush_hour_start_time');
            $table->time('rush_hour_end_time');
            $table->time('evening_rush_start_time');
            $table->time('evening_rush_end_time');
            $table->decimal('night_fee_multiplier', 3, 2);
            $table->time('night_start_time');
            $table->time('night_end_time');
            $table->decimal('express_multiplier', 3, 2);
            $table->decimal('default_commission_percentage', 5, 2);
            $table->decimal('company_commission_percentage', 5, 2);
            $table->decimal('rider_search_radius_km', 5, 2);
            $table->decimal('max_delivery_distance_km', 8, 2);
            $table->decimal('min_withdrawal_amount', 10, 2);
            $table->decimal('max_withdrawal_amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_configs');
    }
};
