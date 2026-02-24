<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->string('raised_by_type'); // User, Rider
            $table->unsignedBigInteger('raised_by_id');
            $table->enum('category', ['non_delivery', 'damaged_item', 'wrong_item', 'wrong_address', 'rider_behavior', 'user_behavior', 'payment_issue', 'other']);
            $table->text('description');
            $table->json('evidence_photos')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed', 'rejected'])->default('open');
            $table->text('resolution')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('admins');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
