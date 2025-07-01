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
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->string('key');
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->json('value')->nullable();
            $table->enum('reset_period', ['never', 'daily', 'monthly', 'yearly'])->default('never');
            $table->timestamps();

            $table->unique(['plan_id', 'key']);
            $table->index(['key', 'reset_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};