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
        Schema::create('together_ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('service_type'); // 'story' (text) or 'image'
            $table->string('model_id')->nullable();
            $table->decimal('estimated_cost', 10, 4); // USD
            $table->timestamps();

            // Indexes for efficient daily-limit lookups
            $table->index(['user_id', 'service_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('together_ai_usage');
    }
};
