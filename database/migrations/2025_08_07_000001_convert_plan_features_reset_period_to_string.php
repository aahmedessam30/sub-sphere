<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Convert reset_period column from enum to string for better PHP enum compatibility
     */
    public function up(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            $table->string('reset_period_temp', 20)->default('never');
        });

        DB::statement('UPDATE plan_features SET reset_period_temp = reset_period');

        Schema::table('plan_features', function (Blueprint $table) {
            $table->dropColumn('reset_period');
        });

        Schema::table('plan_features', function (Blueprint $table) {
            $table->renameColumn('reset_period_temp', 'reset_period');
        });

        Schema::table('plan_features', function (Blueprint $table) {
            $table->index(['key', 'reset_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex(['key', 'reset_period']);
        });

        Schema::table('plan_features', function (Blueprint $table) {
            // Convert back to enum
            $table->enum('reset_period_temp', ['never', 'daily', 'monthly', 'yearly'])->default('never');
        });

        // Copy data back
        DB::statement('UPDATE plan_features SET reset_period_temp = reset_period');

        Schema::table('plan_features', function (Blueprint $table) {
            $table->dropColumn('reset_period');
        });

        Schema::table('plan_features', function (Blueprint $table) {
            $table->renameColumn('reset_period_temp', 'reset_period');
        });

        // Recreate the index
        Schema::table('plan_features', function (Blueprint $table) {
            $table->index(['key', 'reset_period']);
        });
    }
};