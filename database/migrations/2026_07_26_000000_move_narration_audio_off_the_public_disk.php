<?php

use App\Http\Controllers\Api\V1\PageAudioController;
use App\Models\StoryPage;
use App\Services\MediaStorageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Narration generated before this deploy sits on the default disk, which is the
 * local "public" one on staging and production — symlinked into public/storage
 * and served by nginx to anyone who counts story IDs upwards. Moving the code
 * to the media disk only protects narration generated from now on, so the
 * already-exposed files have to be relocated too.
 *
 * Each row's path is derived from the story id and page number, never from the
 * stored URL, so nothing here can be pointed at an arbitrary file.
 */
return new class extends Migration
{
    public function up(): void
    {
        $from = config('filesystems.default');
        $to = config('filesystems.media');

        // Nothing to move if narration was already landing on the media disk.
        if ($from === $to) {
            return;
        }

        StoryPage::query()
            ->whereNotNull('audio_url')
            ->chunkById(200, function ($pages) use ($from, $to) {
                foreach ($pages as $page) {
                    $this->relocate($page, $from, $to);
                }
            });
    }

    public function down(): void
    {
        // Deliberately not reversed: putting children's narration back on a
        // publicly served disk is the bug this migration exists to fix.
    }

    private function relocate(StoryPage $page, string $from, string $to): void
    {
        $path = MediaStorageService::audioPath($page->story_id, $page->page_number);

        try {
            if (Storage::disk($from)->exists($path)) {
                Storage::disk($to)->put($path, Storage::disk($from)->get($path));
                Storage::disk($from)->delete($path);
            }
        } catch (Throwable $e) {
            // A storage hiccup must not brick the deploy. The row keeps its old
            // URL and the endpoint regenerates the narration on next request.
            Log::error('Failed to relocate narration off the public disk', [
                'story_id' => $page->story_id,
                'page_number' => $page->page_number,
                'error_message' => $e->getMessage(),
            ]);

            return;
        }

        $page->forceFill([
            'audio_url' => PageAudioController::audioUrl($page->story_id, $page->page_number),
        ])->save();
    }
};
