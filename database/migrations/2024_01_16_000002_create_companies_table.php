<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('logo')->nullable();
            $table->string('cac_number')->nullable();
            $table->string('cac_document')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->enum('status', ['pending', 'approved', 'active', 'suspended', 'banned'])->default('pending');
            $table->integer('total_riders')->default(0);
            $table->decimal('commission_rate', 5, 2)->default(20.00);
            $table->boolean('is_verified')->default(false);
            $table->json('verification_documents')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
