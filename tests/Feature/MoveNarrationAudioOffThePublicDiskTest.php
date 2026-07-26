<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\StoryPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The narration already sitting in public/storage on staging and production is
 * the live half of the exposure, so the migration that relocates it is worth
 * covering directly rather than trusting it to run right on the first deploy.
 */
class MoveNarrationAudioOffThePublicDiskTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_07_26_000000_move_narration_audio_off_the_public_disk.php'
        );

        $migration->up();
    }

    /** @test */
    public function it_moves_existing_narration_to_the_media_disk_and_repoints_the_row()
    {
        config(['filesystems.default' => 'public', 'filesystems.media' => 'media']);
        Storage::fake('public');
        Storage::fake('media');

        $story = Story::factory()->create();
        $path = "stories/{$story->id}/pages/1/narration.mp3";
        Storage::disk('public')->put($path, 'exposed-mp3-bytes');

        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'audio_url' => "https://api.example.test/storage/{$path}",
        ]);

        $this->runMigration();

        // The publicly reachable copy is gone, and the bytes survived the move.
        Storage::disk('public')->assertMissing($path);
        Storage::disk('media')->assertExists($path);
        $this->assertSame('exposed-mp3-bytes', Storage::disk('media')->get($path));

        $this->assertSame(
            route('stories.pages.audio', ['story' => $story->id, 'pageNumber' => 1]),
            $page->refresh()->audio_url
        );
    }

    /** @test */
    public function it_leaves_pages_with_no_narration_alone()
    {
        config(['filesystems.default' => 'public', 'filesystems.media' => 'media']);
        Storage::fake('public');
        Storage::fake('media');

        $page = StoryPage::factory()->create(['audio_url' => null]);

        $this->runMigration();

        $this->assertNull($page->refresh()->audio_url);
    }

    /** @test */
    public function it_repoints_a_row_whose_file_has_already_gone_missing()
    {
        // No file to move, but the row still advertises a public URL — leaving it
        // there would keep handing out a dead public link.
        config(['filesystems.default' => 'public', 'filesystems.media' => 'media']);
        Storage::fake('public');
        Storage::fake('media');

        $story = Story::factory()->create();
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 3,
            'audio_url' => "https://api.example.test/storage/stories/{$story->id}/pages/3/narration.mp3",
        ]);

        $this->runMigration();

        $this->assertSame(
            route('stories.pages.audio', ['story' => $story->id, 'pageNumber' => 3]),
            $page->refresh()->audio_url
        );
    }

    /** @test */
    public function it_does_nothing_when_narration_already_lives_on_the_media_disk()
    {
        config(['filesystems.default' => 'media', 'filesystems.media' => 'media']);
        Storage::fake('media');

        $page = StoryPage::factory()->create(['audio_url' => 'https://cdn.example.test/whatever.mp3']);

        $this->runMigration();

        $this->assertSame('https://cdn.example.test/whatever.mp3', $page->refresh()->audio_url);
    }
}
