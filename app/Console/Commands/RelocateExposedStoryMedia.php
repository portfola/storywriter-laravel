<?php

namespace App\Console\Commands;

use App\Models\StoryPage;
use App\Services\MediaStorageService;
use Illuminate\Console\Command;
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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:relocate-exposed
                            {--dry-run : Report what would move without touching anything}
                            {--chunk=200 : How many story pages to load at a time}';

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

    private int $moved = 0;

    private int $skipped = 0;

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

        $this->line($dryRun
            ? 'Dry run: nothing will be written, moved, or deleted.'
            : 'Moving story media off the "'.self::SOURCE_DISK.'" disk.');
        $this->line('Destination disk: '.$destination);
        $this->newLine();

        StoryPage::query()
            ->where(function ($query) {
                $query->whereNotNull('audio_url')->orWhereNotNull('image_url');
            })
            ->chunkById(max(1, (int) $this->option('chunk')), function ($pages) use ($destination, $dryRun) {
                foreach ($pages as $page) {
                    $this->relocate($page, 'image_url', MediaStorageService::imagePath($page->story_id, $page->page_number), $destination, $dryRun);
                    $this->relocate($page, 'audio_url', MediaStorageService::audioPath($page->story_id, $page->page_number), $destination, $dryRun);
                }
            });

        $this->newLine();
        $this->line(($dryRun ? 'Would move' : 'Moved').": {$this->moved}");
        $this->line("Left alone (nothing on the public disk): {$this->skipped}");

        if ($this->failed > 0) {
            $this->warn("Failed: {$this->failed} — see the output above. Re-running is safe.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Move one file for one page, and repoint its column only if that worked.
     *
     * The path comes from the story id and page number, never from the value in
     * the column, so a row holding something unexpected — a stale absolute URL
     * from before media moved to stored paths, say — cannot aim this at an
     * arbitrary file.
     */
    private function relocate(StoryPage $page, string $column, string $path, string $destination, bool $dryRun): void
    {
        $source = Storage::disk(self::SOURCE_DISK);

        try {
            if (! $source->exists($path)) {
                $this->skipped++;

                return;
            }

            if ($dryRun) {
                $this->line("  would move {$path} ({$column} on page {$page->id})");
                $this->moved++;

                return;
            }

            $bytes = $source->get($path);

            if ($bytes === null) {
                throw new \RuntimeException('the file disappeared between the check and the read');
            }

            if (! Storage::disk($destination)->put($path, $bytes)) {
                throw new \RuntimeException("the write to the [{$destination}] disk failed");
            }

            // Only now is it safe to drop the exposed copy and repoint the row.
            // Deleting first would lose the file if the write above silently
            // half-succeeded; repointing first would leave the column pointing
            // at a disk that has nothing at that path.
            $source->delete($path);

            $page->forceFill([$column => $path])->save();

            $this->moved++;
        } catch (Throwable $e) {
            // One bad file must not stop the rest. The row keeps whatever it had,
            // so a re-run picks it up again.
            $this->failed++;

            $this->warn("  failed on {$path}: {$e->getMessage()}");
        }
    }
}
