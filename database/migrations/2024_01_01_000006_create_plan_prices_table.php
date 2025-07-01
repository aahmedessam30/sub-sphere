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
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_pricing_id')->constrained('plan_pricings')->onDelete('cascade');
            $table->string('currency_code', 3); // ISO 4217 currency codes (USD, EUR, GBP, etc.)
            $table->decimal('amount', 10, 2); // Amount in the specified currency
            $table->timestamps();

            $table->unique(['plan_pricing_id', 'currency_code']); // One price per currency per pricing
            $table->index(['currency_code', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
