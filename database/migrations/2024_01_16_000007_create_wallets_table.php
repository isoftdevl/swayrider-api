<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type'); // User, Rider, Company
            $table->unsignedBigInteger('owner_id');
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->decimal('total_credited', 15, 2)->default(0.00);
            $table->decimal('total_debited', 15, 2)->default(0.00);
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
