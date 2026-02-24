<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referrer_type'); // User, Rider
            $table->unsignedBigInteger('referrer_id');
            $table->string('referred_type'); // User, Rider
            $table->unsignedBigInteger('referred_id');
            $table->string('referral_code')->unique();
            $table->decimal('reward_amount', 10, 2)->default(0.00);
            $table->boolean('reward_claimed')->default(false);
            $table->timestamp('reward_claimed_at')->nullable();
            $table->boolean('condition_met')->default(false);
            $table->timestamp('condition_met_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
