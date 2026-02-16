<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('story_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // --- THE CATCH-ALL COLUMN ---
            $table->json('story_inputs')->nullable(); // Stores EVERYTHING user sent
            // ----------------------------

            // Metadata (Keep these separate for easy sorting)
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); // Device info
            $table->integer('generation_time_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_analytics');
    }
};
