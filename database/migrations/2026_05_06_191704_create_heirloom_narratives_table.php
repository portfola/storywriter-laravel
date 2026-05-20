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
        Schema::create('heirloom_narratives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('heirloom_sessions')->cascadeOnDelete();
            $table->foreignId('transcript_id')->constrained('heirloom_transcripts')->cascadeOnDelete();
            $table->longText('narrative_text');
            $table->string('format')->default('memoir'); // memoir, letter, timeline
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('share_token')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heirloom_narratives');
    }
};
