<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->text('pickup_address');
            $table->decimal('pickup_latitude', 10, 8);
            $table->decimal('pickup_longitude', 11, 8);
            $table->string('pickup_contact_name');
            $table->string('pickup_contact_phone');
            $table->text('pickup_instructions')->nullable();

            $table->text('dropoff_address');
            $table->decimal('dropoff_latitude', 10, 8);
            $table->decimal('dropoff_longitude', 11, 8);
            $table->string('dropoff_contact_name');
            $table->string('dropoff_contact_phone');
            $table->text('dropoff_instructions')->nullable();

            $table->enum('package_size', ['small', 'medium', 'large'])->default('small');
            $table->text('package_description')->nullable();
            $table->decimal('package_value', 15, 2)->nullable();
            $table->decimal('package_weight', 8, 2)->nullable();
            $table->string('package_photo')->nullable();

            $table->decimal('distance_km', 8, 2);
            $table->decimal('base_price', 10, 2);
            $table->decimal('distance_price', 10, 2);
            $table->decimal('size_fee', 10, 2)->default(0.00);
            $table->decimal('time_fee', 10, 2)->default(0.00);
            $table->decimal('urgency_multiplier', 3, 2)->default(1.00);
            $table->decimal('total_price', 10, 2);
            $table->decimal('platform_commission', 10, 2);
            $table->decimal('rider_earning', 10, 2);
            $table->decimal('company_commission', 10, 2)->default(0.00);

            $table->enum('status', ['pending', 'assigned', 'rider_accepted', 'picked_up', 'in_transit', 'arrived', 'delivered', 'cancelled', 'failed'])->default('pending');
            $table->enum('urgency', ['normal', 'express'])->default('normal');
            $table->string('delivery_pin', 6)->nullable();
            $table->string('delivery_otp', 6)->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('rider_accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->text('failed_reason')->nullable();

            $table->string('pickup_proof_photo')->nullable();
            $table->string('delivery_proof_photo')->nullable();
            $table->string('recipient_signature')->nullable();
            $table->text('delivery_notes')->nullable();

            $table->integer('estimated_duration_minutes')->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
