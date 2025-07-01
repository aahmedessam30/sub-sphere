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
        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('key'); // Feature key (e.g., "ads_limit", "api_calls")
            $table->integer('used')->default(0); // Amount used
            $table->timestamp('last_used_at')->nullable(); // When last used
            $table->timestamps();

            $table->unique(['subscription_id', 'key']); // One usage record per feature per subscription
            $table->index(['key', 'used']);
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_usages');
    }
};
