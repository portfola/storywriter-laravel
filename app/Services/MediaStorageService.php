<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Stores generated story media (page illustrations, narration audio) on our own
 * disk so it outlives the short-lived URLs the AI providers hand back.
 *
 * Which disk is used comes from FILESYSTEM_DISK: the "public" local disk in
 * development, S3 in staging/production.
 */
class MediaStorageService
{
    /**
     * Path for a page's illustration.
     */
    public static function imagePath(int $storyId, int $pageNumber): string
    {
        return "stories/{$storyId}/pages/{$pageNumber}/image.png";
    }

    /**
     * Path for a page's narration audio.
     */
    public static function audioPath(int $storyId, int $pageNumber): string
    {
        return "stories/{$storyId}/pages/{$pageNumber}/narration.mp3";
    }

    /**
     * Download a file from a remote URL and store it under $path on our disk.
     *
     * @return string URL of the stored copy
     *
     * @throws RuntimeException if the download or the write fails
     */
    public function storeFromUrl(string $sourceUrl, string $path): string
    {
        try {
            $response = Http::connectTimeout(10)->timeout(60)->get($sourceUrl);
        } catch (ConnectionException $e) {
            // A hung or unreachable provider CDN is the likeliest failure here, and
            // it arrives as ConnectionException, which is not a RuntimeException.
            // Callers get one exception type to catch either way.
            throw new RuntimeException(
                "Failed to download media from {$sourceUrl}: {$e->getMessage()}",
                previous: $e
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Failed to download media from {$sourceUrl} (HTTP {$response->status()})"
            );
        }

        return $this->storeBytes($response->body(), $path);
    }

    /**
     * Store raw bytes under $path on our disk.
     *
     * @return string URL of the stored file
     *
     * @throws RuntimeException if the write fails
     */
    public function storeBytes(string $bytes, string $path): string
    {
        $disk = $this->disk();

        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw new RuntimeException("Failed to write media to the [{$disk}] disk at {$path}");
        }

        return $this->url($path);
    }

    /**
     * Whether a file is already stored at $path.
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk())->exists($path);
    }

    /**
     * Raw bytes of an already-stored file, or null if it isn't there.
     */
    public function get(string $path): ?string
    {
        return Storage::disk($this->disk())->get($path);
    }

    /**
     * The disk media is written to.
     */
    public function disk(): string
    {
        return config('filesystems.default');
    }

    /**
     * URL for an already-stored file.
     *
     * Both configured disks already return an absolute URL: the public disk is
     * configured with APP_URL as its base, and S3 returns the bucket URL. The
     * app URL is only prepended as a backstop, for a disk whose "url" is unset
     * or a blank APP_URL — the tablet app can't load a root-relative path.
     */
    public function url(string $path): string
    {
        $url = Storage::disk($this->disk())->url($path);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
