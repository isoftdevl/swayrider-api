<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->unique()->constrained('riders')->onDelete('cascade');
            $table->enum('id_type', ['national_id', 'drivers_license', 'voters_card', 'international_passport']);
            $table->string('id_number');
            $table->string('id_front_photo');
            $table->string('id_back_photo');
            $table->string('selfie_photo');
            $table->string('bike_registration_number');
            $table->string('bike_photo');
            $table->string('bike_papers')->nullable();
            $table->string('police_clearance')->nullable();
            $table->string('insurance_certificate')->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone');
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('admins');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_profiles');
    }
};
