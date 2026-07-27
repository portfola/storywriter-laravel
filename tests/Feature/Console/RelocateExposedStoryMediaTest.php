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
    public function it_moves_exposed_files_that_no_row_points_at()
    {
        $this->fakeBothDisks();

        // Nothing deletes media when a story is deleted, and a page insert that
        // fails after the image is stored leaves the file behind. Those files
        // are exposed exactly like any other, so they have to move too.
        $orphan = 'stories/4242/pages/1/image.png';

        // Something under the prefix that is not media in a shape we write. It
        // cannot repoint anything, but it is still sitting in public/storage.
        $unrecognised = 'stories/4242/pages/1/thumbnail.webp';

        Storage::disk('public')->put($orphan, 'orphan-bytes');
        Storage::disk('public')->put($unrecognised, 'thumb-bytes');

        $this->artisan('media:relocate-exposed')->assertExitCode(0);

        Storage::disk('public')->assertMissing($orphan);
        Storage::disk('public')->assertMissing($unrecognised);
        $this->assertSame('orphan-bytes', Storage::disk('private')->get($orphan));
        $this->assertSame('thumb-bytes', Storage::disk('private')->get($unrecognised));
    }

    /** @test */
    public function it_fails_loudly_when_the_exposed_copy_cannot_be_deleted()
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

        // The exposed files on the box were written by php-fpm; the deploy user
        // running this may not be able to unlink them. The local disk is
        // configured not to throw, so delete() returns false and says nothing.
        $directory = storage_path("framework/testing/disks/public/stories/{$story->id}/pages/1");
        chmod($directory, 0555);

        try {
            $this->artisan('media:relocate-exposed')
                ->expectsOutputToContain('still public')
                ->assertExitCode(1);

            // The file is still exposed, so the run must not claim it moved.
            Storage::disk('public')->assertExists($audioPath);

            // The row is repointed all the same: the destination has the file,
            // and the next run finds the exposed copy still there to delete.
            $this->assertSame($audioPath, $page->refresh()->audio_url);
            $this->assertSame('mp3-bytes', Storage::disk('private')->get($audioPath));
        } finally {
            chmod($directory, 0755);
        }
    }

    /** @test */
    public function it_does_not_overwrite_a_file_already_on_the_destination()
    {
        $this->fakeBothDisks();

        $story = Story::factory()->create();
        $imagePath = "stories/{$story->id}/pages/1/image.png";

        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => $imagePath,
            'audio_url' => null,
        ]);

        // Writes have been going to the destination since the disk switch, so a
        // file already there is the newer one. The stale public copy must not
        // replace it — it just has to stop being public.
        Storage::disk('private')->put($imagePath, 'current-bytes');
        Storage::disk('public')->put($imagePath, 'stale-bytes');

        $this->artisan('media:relocate-exposed')->assertExitCode(0);

        $this->assertSame('current-bytes', Storage::disk('private')->get($imagePath));
        Storage::disk('public')->assertMissing($imagePath);
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
    public function it_never_reads_the_path_to_move_out_of_the_database()
    {
        $this->fakeBothDisks();

        $story = Story::factory()->create();
        $audioPath = "stories/{$story->id}/pages/1/narration.mp3";

        // A row pointing somewhere else entirely. Paths come from listing the
        // public disk under stories/, and the column is never read, so nothing
        // outside that prefix can be reached through a row.
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
        Storage::disk('private')->assertMissing('unrelated/secret.txt');
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
