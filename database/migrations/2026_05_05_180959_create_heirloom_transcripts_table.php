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
        Schema::create('heirloom_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('heirloom_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('transcript_text');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('language')->default('en');
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heirloom_transcripts');
    }
};
