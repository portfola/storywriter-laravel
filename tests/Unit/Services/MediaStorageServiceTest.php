<?php

namespace Tests\Unit\Services;

use App\Services\MediaStorageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * MediaStorageService is what keeps generated illustrations and narration from
 * disappearing when the provider's temporary URL expires, so these cover the
 * write paths and the URL it hands back.
 */
class MediaStorageServiceTest extends TestCase
{
    private MediaStorageService $storage;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        $this->storage = new MediaStorageService;
    }

    /** @test */
    public function it_builds_predictable_paths_for_page_media()
    {
        $this->assertSame('stories/7/pages/3/image.png', MediaStorageService::imagePath(7, 3));
        $this->assertSame('stories/7/pages/3/narration.mp3', MediaStorageService::audioPath(7, 3));
    }

    /** @test */
    public function store_bytes_writes_to_the_configured_disk_and_returns_the_path()
    {
        $path = $this->storage->storeBytes('raw-mp3-bytes', 'stories/1/pages/1/narration.mp3');

        Storage::disk('public')->assertExists('stories/1/pages/1/narration.mp3');
        $this->assertSame('raw-mp3-bytes', Storage::disk('public')->get('stories/1/pages/1/narration.mp3'));

        // A path, not a URL: this is what gets saved, and a URL for a private
        // bucket stops working the moment its signature runs out.
        $this->assertSame('stories/1/pages/1/narration.mp3', $path);
    }

    /** @test */
    public function store_from_url_downloads_the_remote_file_and_stores_it()
    {
        Http::fake([
            'cdn.example.com/*' => Http::response('remote-png-bytes'),
        ]);

        $path = $this->storage->storeFromUrl(
            'https://cdn.example.com/expiring/image.png',
            'stories/2/pages/4/image.png'
        );

        Storage::disk('public')->assertExists('stories/2/pages/4/image.png');
        $this->assertSame('remote-png-bytes', Storage::disk('public')->get('stories/2/pages/4/image.png'));
        $this->assertSame('stories/2/pages/4/image.png', $path);
    }

    /** @test */
    public function store_from_url_throws_when_the_download_fails()
    {
        Http::fake([
            'cdn.example.com/*' => Http::response('gone', 404),
        ]);

        $this->expectException(RuntimeException::class);

        $this->storage->storeFromUrl('https://cdn.example.com/gone.png', 'stories/3/pages/1/image.png');
    }

    /** @test */
    public function store_from_url_turns_a_connection_failure_into_a_runtime_exception()
    {
        // Callers only catch RuntimeException, so a hung CDN has to arrive as one.
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(RuntimeException::class);

        $this->storage->storeFromUrl('https://cdn.example.com/slow.png', 'stories/4/pages/1/image.png');
    }

    /** @test */
    public function exists_and_get_read_back_what_was_stored()
    {
        $path = 'stories/5/pages/2/narration.mp3';

        $this->assertFalse($this->storage->exists($path));

        $this->storage->storeBytes('stored-bytes', $path);

        $this->assertTrue($this->storage->exists($path));
        $this->assertSame('stored-bytes', $this->storage->get($path));
    }

    /** @test */
    public function temporary_url_signs_a_stored_path_with_an_expiry()
    {
        // The "local" disk has serve enabled, so it signs URLs the same way the
        // real S3 bucket does — close enough to prove the signing path works.
        // Not faked, because faking a disk drops the serve flag that does the
        // signing. Nothing is written either: signing a path doesn't read it.
        config(['filesystems.default' => 'local', 'filesystems.media_url_ttl_minutes' => 30]);

        $url = $this->storage->temporaryUrl('stories/8/pages/1/image.png');

        $this->assertStringContainsString('stories/8/pages/1/image.png', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
    }

    /** @test */
    public function temporary_url_honours_the_configured_lifetime()
    {
        config(['filesystems.default' => 'local', 'filesystems.media_url_ttl_minutes' => 90]);

        $url = $this->storage->temporaryUrl('stories/9/pages/1/image.png');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        // Within a minute of 90 minutes out, allowing for the clock moving.
        $this->assertEqualsWithDelta(now()->addMinutes(90)->timestamp, (int) $query['expires'], 60);
    }

    /** @test */
    public function temporary_url_falls_back_to_a_plain_url_on_a_disk_that_cannot_sign()
    {
        // The public disk used in development can't sign, and doesn't need to —
        // it's symlinked into public/ and served to anyone regardless.
        $url = $this->storage->temporaryUrl('stories/10/pages/1/image.png');

        $this->assertStringContainsString('stories/10/pages/1/image.png', $url);
        $this->assertStringNotContainsString('signature=', $url);
    }

    /** @test */
    public function temporary_url_leaves_an_absolute_url_alone()
    {
        // Rows written before media moved to stored paths hold a whole URL. There
        // is nothing to sign in one of those, so it comes back as it went in.
        $legacy = 'https://cdn.example.com/old/image.png';

        $this->assertSame($legacy, $this->storage->temporaryUrl($legacy));
    }

    /** @test */
    public function temporary_url_is_null_when_there_is_nothing_stored()
    {
        // A page with no illustration yet, which is every page but the cover until
        // someone asks for one.
        $this->assertNull($this->storage->temporaryUrl(null));
        $this->assertNull($this->storage->temporaryUrl(''));
    }

    /** @test */
    public function url_falls_back_to_the_app_url_when_the_disk_returns_a_relative_path()
    {
        // A disk with no "url" configured returns a root-relative path, which the
        // tablet app can't load — the app URL is prepended as a backstop.
        config([
            'app.url' => 'https://api.example.test',
            'filesystems.default' => 'relative',
            'filesystems.disks.relative' => ['driver' => 'local', 'root' => storage_path('app/relative')],
        ]);
        Storage::fake('relative');

        $this->assertSame(
            'https://api.example.test/storage/stories/6/pages/1/image.png',
            $this->storage->url('stories/6/pages/1/image.png')
        );
    }
}
