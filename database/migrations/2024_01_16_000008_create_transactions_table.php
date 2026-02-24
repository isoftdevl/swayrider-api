<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->enum('category', ['funding', 'delivery_payment', 'delivery_earning', 'commission', 'withdrawal', 'refund', 'company_payout']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->string('payment_method')->nullable();
            $table->string('paystack_reference')->nullable()->unique();
            $table->timestamps();

            $table->index('wallet_id');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
