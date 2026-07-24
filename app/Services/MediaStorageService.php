<?php

namespace App\Services;

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
        $response = Http::connectTimeout(10)->timeout(60)->get($sourceUrl);

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
     * The disk media is written to.
     */
    public function disk(): string
    {
        return config('filesystems.default');
    }

    /**
     * URL for an already-stored file.
     *
     * Local disks return a root-relative path ("/storage/..."), which the tablet
     * app can't load, so those get the app URL prepended. S3 already returns an
     * absolute URL and is left alone.
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
