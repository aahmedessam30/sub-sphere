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
        Schema::create('plan_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->json('label');
            $table->integer('duration_in_days'); 
            $table->decimal('price', 10, 2); 
            $table->boolean('is_best_offer')->default(false);
            $table->timestamps();

            $table->index(['plan_id', 'is_best_offer']);
            $table->index('duration_in_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_pricings');
    }
};