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
        Schema::create('story_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            $table->smallInteger('page_number')->unsigned();
            $table->text('content');
            $table->text('illustration_prompt')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['story_id', 'page_number']);
        });

        Schema::table('stories', function (Blueprint $table) {
            $table->text('characters_description')->nullable()->after('prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_pages');

        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('characters_description');
        });
    }
};
