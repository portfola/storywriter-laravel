<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Pins the two things that have to stay true about story media URLs.
 *
 * The columns hold a path, and the URL a client is handed is one it can GET.
 * Both were nearly broken by a fix that wrote a POST-only endpoint URL into
 * audio_url: the signing code passes absolute URLs through untouched, so it
 * reached the tablet and the dashboard player as-is and answered 405 to the
 * only verb either of them uses. Nothing in the suite noticed, because the
 * tests checked what was written to the column, not what a client could do
 * with it. These are those tests.
 */
class SignedMediaUrlContractTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMediaDisk(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
    }

    /**
     * Fail if the URL matches a route that will not answer a GET.
     *
     * Not matching any route is the normal, healthy case — a signed bucket URL
     * or a /storage path is not something the router knows about.
     */
    private function assertFetchableByGet(?string $url, string $label): void
    {
        $this->assertNotNull($url, "{$label} should not be null here");

        try {
            Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (NotFoundHttpException) {
            // Not an app route at all — a storage or bucket URL. Fine.
        } catch (MethodNotAllowedHttpException $e) {
            $this->fail("{$label} points at {$url}, which does not answer GET. A player can only GET it.");
        }
    }

    /** @test */
    public function the_narration_url_handed_to_clients_answers_a_get()
    {
        $this->fakeMediaDisk();

        Http::fake([
            'api.elevenlabs.io/*' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'content' => 'Once upon a time.',
            'audio_url' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/1/audio")
            ->assertOk();

        $page->refresh();

        // The column holds a path, not a URL. Anything absolute sails straight
        // through the signing code and reaches the client unchanged.
        $this->assertSame(
            "stories/{$story->id}/pages/1/narration.mp3",
            $page->audio_url,
            'audio_url should hold the stored path so it can be signed on the way out'
        );

        $this->assertFetchableByGet($page->signed_audio_url, 'signed_audio_url');
    }

    /** @test */
    public function the_audio_url_in_the_story_payload_answers_a_get()
    {
        $this->fakeMediaDisk();

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $audioPath = "stories/{$story->id}/pages/1/narration.mp3";
        $imagePath = "stories/{$story->id}/pages/1/image.png";

        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'audio_url' => $audioPath,
            'image_url' => $imagePath,
        ]);

        Storage::disk('public')->put($audioPath, 'mp3-bytes');
        Storage::disk('public')->put($imagePath, 'png-bytes');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/stories/{$story->id}")
            ->assertOk();

        $page = $response->json('data.pages.0');

        $this->assertFetchableByGet($page['audioUrl'], 'pages[0].audioUrl');
        $this->assertFetchableByGet($page['imageUrl'], 'pages[0].imageUrl');
    }
}
