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
    public function store_bytes_writes_to_the_configured_disk_and_returns_its_url()
    {
        $url = $this->storage->storeBytes('raw-mp3-bytes', 'stories/1/pages/1/narration.mp3');

        Storage::disk('public')->assertExists('stories/1/pages/1/narration.mp3');
        $this->assertSame('raw-mp3-bytes', Storage::disk('public')->get('stories/1/pages/1/narration.mp3'));

        // The tablet app can't load a root-relative path, so the URL is absolute.
        $this->assertStringStartsWith('http', $url);
        $this->assertStringContainsString('stories/1/pages/1/narration.mp3', $url);
    }

    /** @test */
    public function store_from_url_downloads_the_remote_file_and_stores_it()
    {
        Http::fake([
            'cdn.example.com/*' => Http::response('remote-png-bytes'),
        ]);

        $url = $this->storage->storeFromUrl(
            'https://cdn.example.com/expiring/image.png',
            'stories/2/pages/4/image.png'
        );

        Storage::disk('public')->assertExists('stories/2/pages/4/image.png');
        $this->assertSame('remote-png-bytes', Storage::disk('public')->get('stories/2/pages/4/image.png'));
        $this->assertStringContainsString('stories/2/pages/4/image.png', $url);
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
