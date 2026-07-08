<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heirloom_narratives', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->foreignId('session_id')
                ->nullable()
                ->change();
            $table->foreign('session_id')
                ->references('id')
                ->on('heirloom_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('heirloom_narratives', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->foreignId('session_id')
                ->nullable(false)
                ->change();
            $table->foreign('session_id')
                ->references('id')
                ->on('heirloom_sessions')
                ->cascadeOnDelete();
        });
    }
};