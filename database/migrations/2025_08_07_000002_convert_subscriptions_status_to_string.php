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
     * Convert status column from enum to string for better PHP enum compatibility
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add temporary string column
            $table->string('status_temp', 20)->default('pending');
        });

        // Copy data from old column to new column
        DB::statement('UPDATE subscriptions SET status_temp = status');

        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop indexes that reference the old column
            $table->dropIndex(['status', 'ends_at']);
            $table->dropIndex(['status', 'trial_ends_at']);

            // Drop the old enum column
            $table->dropColumn('status');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Rename the temp column to the original name
            $table->renameColumn('status_temp', 'status');
        });

        // Recreate the indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'ends_at']);
            $table->index(['status', 'trial_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop the indexes first
            $table->dropIndex(['status', 'ends_at']);
            $table->dropIndex(['status', 'trial_ends_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Add temporary enum column
            $table->enum('status_temp', ['pending', 'trial', 'active', 'inactive', 'canceled', 'expired'])->default('pending');
        });

        // Copy data back
        DB::statement('UPDATE subscriptions SET status_temp = status');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('status_temp', 'status');
        });

        // Recreate the indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'ends_at']);
            $table->index(['status', 'trial_ends_at']);
        });
    }
};