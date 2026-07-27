<?php

namespace App\Console\Commands;

use App\Models\StoryPage;
use App\Services\MediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves story media that is already sitting on the publicly served local disk
 * over to wherever media is supposed to live now.
 *
 * Staging and production ran for a while with FILESYSTEM_DISK=public and
 * `storage:link`, so every illustration and narration recording written in that
 * time is under public/storage, served by nginx to anyone who counts story IDs
 * upwards. Pointing new writes at the private bucket does not help those files —
 * they stay where they are until something moves them. This is that something.
 *
 * It walks the public disk rather than the story_pages table. What makes a file
 * a problem is that it is sitting in public/storage, not that a row points at
 * it, and plenty of them have no row: nothing deletes media when a story is
 * deleted, and a page insert that fails after the image is stored leaves the
 * file behind. Walking rows would move the media whose owner still exists and
 * quietly leave the rest exposed.
 *
 * Run it AFTER the deploy that switches FILESYSTEM_DISK away from "public".
 * Until then the source and destination are the same disk and there is nowhere
 * to move anything to; the command says so and stops.
 *
 * A command rather than a migration, deliberately. The move only makes sense in
 * one specific ordering, it is worth dry-running against real data first, and a
 * migration that ran early would mark itself done having moved nothing.
 */
class RelocateExposedStoryMedia extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:relocate-exposed
                            {--dry-run : Report what would move without touching anything}
                            {--force : Skip the confirmation prompt in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move story images and narration off the publicly served disk';

    /**
     * The disk the exposed files are on. Named outright rather than read from
     * config: this command exists because of what is on the local public disk
     * right now, and that does not change just because the default disk did.
     */
    private const SOURCE_DISK = 'public';

    /**
     * Everything story media has ever been written under. Both path helpers on
     * MediaStorageService have always produced stories/{id}/pages/{n}/..., so
     * this one prefix covers every file the app has written to this disk.
     */
    private const MEDIA_PREFIX = 'stories';

    private int $moved = 0;

    private int $repointed = 0;

    private int $alreadyOnDestination = 0;

    private int $failed = 0;

    public function handle(MediaStorageService $mediaStorage): int
    {
        $destination = $mediaStorage->disk();

        if ($destination === self::SOURCE_DISK) {
            $this->error('The default disk is still "public", so there is nowhere to move these files to.');
            $this->line('Deploy the FILESYSTEM_DISK switch first, then run this again.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->line($dryRun
            ? 'Dry run: nothing will be written, moved, or deleted.'
            : 'Moving story media off the "'.self::SOURCE_DISK.'" disk.');
        $this->line('Destination disk: '.$destination);
        $this->newLine();

        foreach (Storage::disk(self::SOURCE_DISK)->allFiles(self::MEDIA_PREFIX) as $path) {
            $this->relocate($path, $destination, $dryRun);
        }

        $this->newLine();
        $this->line(($dryRun ? 'Would move' : 'Moved').": {$this->moved}");
        $this->line(($dryRun ? 'Would repoint' : 'Rows repointed').": {$this->repointed}");

        if ($this->alreadyOnDestination > 0) {
            $this->line("Already on the destination, exposed copy deleted: {$this->alreadyOnDestination}");
        }

        if ($this->failed > 0) {
            $this->warn("Failed: {$this->failed} — see the output above. Re-running is safe.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Move one exposed file, and point its page row at it if there is one.
     */
    private function relocate(string $path, string $destination, bool $dryRun): void
    {
        $source = Storage::disk(self::SOURCE_DISK);
        $target = Storage::disk($destination);

        try {
            if ($dryRun) {
                $page = $this->pageFor($path);

                $this->line("  would move {$path}".($page ? " (page {$page->id})" : ' (no page row points at it)'));
                $this->moved++;

                if ($page) {
                    $this->repointed++;
                }

                return;
            }

            // A file already at the destination path is the newer copy: writes
            // have been going there since the disk switch. Never overwrite it
            // with the stale public one — only get rid of the exposed copy.
            if ($target->exists($path)) {
                $this->alreadyOnDestination++;
            } else {
                $bytes = $source->get($path);

                if ($bytes === null) {
                    throw new \RuntimeException('the file could not be read');
                }

                if (! $target->put($path, $bytes)) {
                    throw new \RuntimeException("the write to the [{$destination}] disk failed");
                }
            }

            // Repoint before deleting. The destination holds the file by this
            // point, so the column is never left pointing at a path with nothing
            // behind it — and if the save fails, the exposed copy is still there
            // to be found and moved by the next run.
            $this->repoint($path);

            $source->delete($path);

            // delete() on this disk is configured not to throw, so it returns
            // false and says nothing when it cannot unlink — which is what
            // happens when php-fpm wrote the file and the command runs as the
            // deploy user. That is the one failure that matters most here: it
            // leaves the file public while the run reports success. Check it.
            if ($source->exists($path)) {
                throw new \RuntimeException('the exposed copy could not be deleted, so it is still public');
            }

            $this->moved++;
        } catch (Throwable $e) {
            // One bad file must not stop the rest. Whatever could not be done
            // is left to be picked up by the next run.
            $this->failed++;

            $this->warn("  failed on {$path}: {$e->getMessage()}");
        }
    }

    /**
     * Point a page row at the path the file now lives at.
     *
     * The story and page come out of the path being moved, which came from
     * listing the disk rather than from anything in the database, and the path
     * has to match what MediaStorageService would have written for that story
     * and page exactly. So a row holding an unexpected value cannot aim this at
     * another file, and a file that is not media in the shape we write cannot
     * repoint a row at all — it still gets moved off the public disk.
     */
    private function repoint(string $path): void
    {
        $page = $this->pageFor($path);

        if (! $page) {
            return;
        }

        $column = $this->columnFor($path, $page->story_id, $page->page_number);

        if ($column === null || $page->{$column} === $path) {
            return;
        }

        $page->forceFill([$column => $path])->save();

        $this->repointed++;
    }

    /**
     * The story page this file belongs to, or null if nothing claims it.
     */
    private function pageFor(string $path): ?StoryPage
    {
        if (! preg_match('#^stories/(\d+)/pages/(\d+)/#', $path, $matches)) {
            return null;
        }

        $storyId = (int) $matches[1];
        $pageNumber = (int) $matches[2];

        if ($this->columnFor($path, $storyId, $pageNumber) === null) {
            return null;
        }

        return StoryPage::query()
            ->where('story_id', $storyId)
            ->where('page_number', $pageNumber)
            ->first();
    }

    /**
     * Which column holds this path, or null if it is not one we write.
     */
    private function columnFor(string $path, int $storyId, int $pageNumber): ?string
    {
        return match ($path) {
            MediaStorageService::imagePath($storyId, $pageNumber) => 'image_url',
            MediaStorageService::audioPath($storyId, $pageNumber) => 'audio_url',
            default => null,
        };
    }
}
