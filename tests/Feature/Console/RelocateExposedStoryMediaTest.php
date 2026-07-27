<?php

namespace Tests\Feature\Console;

use App\Models\Story;
use App\Models\StoryPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers media:relocate-exposed, which moves the story images and narration
 * recordings that are already sitting on the publicly served local disk over to
 * the private one, and repoints the rows that pointed at them.
 */
class RelocateExposedStoryMediaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public disk holds the exposed files; "private" stands in for whatever
     * the default disk becomes once the deploy stops pointing at "public".
     */
    private function fakeBothDisks(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        config(['filesystems.default' => 'private']);
        config(['filesystems.disks.private' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/private'),
        ]]);
    }

    /** @test */
    public function it_refuses_to_run_while_the_default_disk_is_still_the_public_one()
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $this->artisan('media:relocate-exposed')
            ->expectsOutputToContain('there is nowhere to move these files to')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_moves_both_the_image_and_the_narration_and_repoints_the_row()
    {
        $this->fakeBothDisks();

        $story = Story::factory()->create();
        $imagePath = "stories/{$story->id}/pages/1/image.png";
        $audioPath = "stories/{$story->id}/pages/1/narration.mp3";

        // The row holds an absolute public URL, as rows written before media
        // moved to stored paths do.
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => Storage::disk('public')->url($imagePath),
            'audio_url' => Storage::disk('public')->url($audioPath),
        ]);

        Storage::disk('public')->put($imagePath, 'png-bytes');
        Storage::disk('public')->put($audioPath, 'mp3-bytes');

        $this->artisan('media:relocate-exposed')->assertExitCode(0);

        // The bytes arrived intact on the private disk...
        Storage::disk('private')->assertExists($imagePath);
        Storage::disk('private')->assertExists($audioPath);
        $this->assertSame('png-bytes', Storage::disk('private')->get($imagePath));
        $this->assertSame('mp3-bytes', Storage::disk('private')->get($audioPath));

        // ...and the exposed copies are gone, which is the whole point.
        Storage::disk('public')->assertMissing($imagePath);
        Storage::disk('public')->assertMissing($audioPath);

        // The row now holds paths, which is what the signing code expects.
        $page->refresh();
        $this->assertSame($imagePath, $page->image_url);
        $this->assertSame($audioPath, $page->audio_url);
    }

    /** @test */
    public function it_leaves_a_row_alone_when_the_file_is_not_on_the_public_disk()
    {
        $this->fakeBothDisks();

        $story = Story::factory()->create();
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => 'https://api.together.ai/expired/thing.png',
            'audio_url' => null,
        ]);

        $this->artisan('media:relocate-exposed')->assertExitCode(0);

        // Nothing moved, so nothing gets rewritten — a row repointed at a path
        // that has no file behind it is worse than the dead URL it replaced.
        $page->refresh();
        $this->assertSame('https://api.together.ai/expired/thing.png', $page->image_url);
        $this->assertNull($page->audio_url);
    }

    /** @test */
    public function a_dry_run_reports_the_files_without_touching_anything()
    {
        $this->fakeBothDisks();

        $story = Story::factory()->create();
        $audioPath = "stories/{$story->id}/pages/1/narration.mp3";

        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => null,
            'audio_url' => Storage::disk('public')->url($audioPath),
        ]);

        Storage::disk('public')->put($audioPath, 'mp3-bytes');

        $this->artisan('media:relocate-exposed --dry-run')
            ->expectsOutputToContain($audioPath)
            ->assertExitCode(0);

        Storage::disk('public')->assertExists($audioPath);
        Storage::disk('private')->assertMissing($audioPath);
        $this->assertSame(Storage::disk('public')->url($audioPath), $page->refresh()->audio_url);
    }

    /** @test */
    public function it_only_touches_the_paths_it_derives_from_the_story_and_page()
    {
        $this->fakeBothDisks();

        $story = Story::factory()->create();
        $audioPath = "stories/{$story->id}/pages/1/narration.mp3";

        // A row pointing somewhere else entirely on the public disk. The command
        // rebuilds paths from the story id and page number and never reads the
        // column, so this file must be left where it is.
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => null,
            'audio_url' => Storage::disk('public')->url('../../.env'),
        ]);

        Storage::disk('public')->put('unrelated/secret.txt', 'not-media');
        Storage::disk('public')->put($audioPath, 'mp3-bytes');

        $this->artisan('media:relocate-exposed')->assertExitCode(0);

        Storage::disk('public')->assertExists('unrelated/secret.txt');
        Storage::disk('public')->assertMissing($audioPath);
    }

    /** @test */
    public function it_is_safe_to_run_twice()
    {
        $this->fakeBothDisks();

        $story = Story::factory()->create();
        $audioPath = "stories/{$story->id}/pages/1/narration.mp3";

        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => null,
            'audio_url' => Storage::disk('public')->url($audioPath),
        ]);

        Storage::disk('public')->put($audioPath, 'mp3-bytes');

        $this->artisan('media:relocate-exposed')->assertExitCode(0);
        $this->artisan('media:relocate-exposed')->assertExitCode(0);

        $this->assertSame('mp3-bytes', Storage::disk('private')->get($audioPath));
        $this->assertSame($audioPath, $page->refresh()->audio_url);
    }
}
