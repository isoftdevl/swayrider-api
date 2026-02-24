<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['deliveries', 'payment_methods', 'ratings', 'promo_code_usage', 'saved_addresses'];

        foreach ($tables as $table) {
            // Attempt to drop existing foreign key (may fail if doesn't exist)
            try {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (\Exception $e) {
                // Ignore failure if FK doesn't exist
            }

            // Create new foreign key pointing to users table
            Schema::table($table, function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to users_backup if necessary
        // Note: This assumes users_backup exists and is the desired target for rollback
        
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users_backup')->cascadeOnDelete();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users_backup')->cascadeOnDelete();
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users_backup')->cascadeOnDelete();
        });

        Schema::table('promo_code_usage', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users_backup')->cascadeOnDelete();
        });

        Schema::table('saved_addresses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users_backup')->cascadeOnDelete();
        });
    }
};
