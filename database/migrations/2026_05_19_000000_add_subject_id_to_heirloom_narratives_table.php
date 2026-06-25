<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heirloom_narratives', function (Blueprint $table) {
            $table->foreignId('subject_id')
                ->nullable()
                ->after('user_id')
                ->constrained('heirloom_subjects')
                ->nullOnDelete();

            $table->dropForeign(['transcript_id']);
            $table->foreignId('transcript_id')
                ->nullable()
                ->change();
            $table->foreign('transcript_id')
                ->references('id')
                ->on('heirloom_transcripts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('heirloom_narratives', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');

            $table->dropForeign(['transcript_id']);
            $table->foreignId('transcript_id')
                ->nullable(false)
                ->change();
            $table->foreign('transcript_id')
                ->references('id')
                ->on('heirloom_transcripts')
                ->cascadeOnDelete();
        });
    }
};
