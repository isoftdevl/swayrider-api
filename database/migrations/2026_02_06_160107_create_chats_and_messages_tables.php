<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $header) {
            $header->id();
            $header->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $header->string('status')->default('open'); // open, closed
            $header->timestamps();
        });

        Schema::create('messages', function (Blueprint $header) {
            $header->id();
            $header->foreignId('chat_id')->constrained()->onDelete('cascade');
            $header->morphs('sender'); // sender_id, sender_type (App\Models\User or App\Models\Rider)
            $header->text('message');
            $header->timestamp('read_at')->nullable();
            $header->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('chats');
    }
};
