<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship to subscriber (User, Team, etc.)
            $table->morphs('subscriber');

            // Plan and pricing references
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->foreignId('plan_pricing_id')->constrained('plan_pricings')->onDelete('cascade');

            // Subscription state
            $table->enum('status', ['pending', 'trial', 'active', 'inactive', 'canceled', 'expired'])
                ->default('pending');
            $table->boolean('is_auto_renewal')->default(true);

            // Important dates
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable(); // Grace period after expiry
            $table->timestamp('trial_ends_at')->nullable(); // Trial period end

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'ends_at']);
            $table->index(['status', 'trial_ends_at']);
            $table->index(['is_auto_renewal', 'ends_at']);
            $table->index('starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
