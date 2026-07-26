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
 *
 * Writes return the object path, not a URL, and that path is what gets persisted.
 * The bucket is private, so a URL is only good for as long as it is signed for —
 * baking one into the database would put a link that 403s from the day it expires
 * into every saved storybook. Call temporaryUrl() when a URL actually leaves the
 * API instead.
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
     * @return string the object path of the stored copy
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
     * @return string the object path it was stored at
     *
     * @throws RuntimeException if the write fails
     */
    public function storeBytes(string $bytes, string $path): string
    {
        $disk = $this->disk();

        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw new RuntimeException("Failed to write media to the [{$disk}] disk at {$path}");
        }

        return $path;
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
     * A URL a client can fetch $stored from, good for a limited time.
     *
     * $stored is what the database holds. Normally that is an object path, which
     * gets signed here. Rows written before media moved to stored paths hold an
     * absolute URL instead, and there is nothing to sign in one of those, so it
     * is handed back untouched — it either still works (the file is on the public
     * disk it was written to) or it is already dead, and re-signing it would not
     * help either way.
     */
    public function temporaryUrl(?string $stored): ?string
    {
        if (blank($stored)) {
            return null;
        }

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        $ttl = now()->addMinutes((int) config('filesystems.media_url_ttl_minutes'));

        try {
            return Storage::disk($this->disk())->temporaryUrl($stored, $ttl);
        } catch (RuntimeException $e) {
            // The local "public" disk used in development can't sign URLs. It is
            // symlinked into public/ and served to anyone regardless, so a plain
            // URL gives away nothing a signed one would have protected.
            return $this->url($stored);
        }
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
