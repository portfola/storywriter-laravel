<?php

namespace Tests\Feature\Api\V1;

use App\Models\ElevenLabsUsage;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the per-page narration endpoint: narration is generated once, stored
 * on the media disk, and handed back from storage on every replay so we never
 * pay ElevenLabs twice for the same page.
 *
 * The media disk is deliberately not the default one — the default is the local
 * "public" disk on staging and production, which nginx serves to anyone.
 */
class PageAudioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Point media storage at throwaway disks so tests never write real files.
     */
    private function fakeMediaDisk(): void
    {
        config(['filesystems.default' => 'public', 'filesystems.media' => 'media']);
        Storage::fake('public');
        Storage::fake('media');
    }

    /** @test */
    public function it_generates_and_stores_narration_when_the_page_has_no_audio_yet()
    {
        $this->fakeMediaDisk();

        Http::fake([
            'api.elevenlabs.io/*' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 2,
            'content' => 'The knight climbed onto the dragon.',
            'audio_url' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/2/audio");

        $response->assertOk();
        $this->assertSame('audio/mpeg', $response->headers->get('Content-Type'));
        $this->assertSame('fake-mp3-bytes', $response->getContent());

        // The narration is kept on the media disk, and nowhere near the publicly
        // served one — these are recordings of children reading their own stories.
        $storedPath = "stories/{$story->id}/pages/2/narration.mp3";
        Storage::disk('media')->assertExists($storedPath);
        $this->assertSame('fake-mp3-bytes', Storage::disk('media')->get($storedPath));
        Storage::disk('public')->assertMissing($storedPath);

        // The page points at the authenticated endpoint, not at a public file.
        $page->refresh();
        $this->assertSame(
            route('stories.pages.audio', ['story' => $story->id, 'pageNumber' => 2]),
            $page->audio_url
        );
        $this->assertStringNotContainsString('narration.mp3', $page->audio_url);

        // Narration is generated from the page's own text, not anything a client sent.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.elevenlabs.io/v1/text-to-speech/')
                && $request['text'] === 'The knight climbed onto the dragon.'
                && $request['model_id'] === config('services.elevenlabs.default_model');
        });

        // The spend is recorded against the user's daily cap.
        $this->assertDatabaseHas('elevenlabs_usage', [
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => strlen('The knight climbed onto the dragon.'),
        ]);
    }

    /** @test */
    public function it_returns_stored_narration_without_calling_elevenlabs_again()
    {
        $this->fakeMediaDisk();

        Http::fake();

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $storedPath = "stories/{$story->id}/pages/1/narration.mp3";
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'content' => 'Once upon a time.',
            'audio_url' => route('stories.pages.audio', ['story' => $story->id, 'pageNumber' => 1]),
        ]);

        Storage::disk('media')->put($storedPath, 'already-stored-mp3');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/1/audio");

        $response->assertOk();
        $this->assertSame('already-stored-mp3', $response->getContent());

        Http::assertNothingSent();
        $this->assertDatabaseCount('elevenlabs_usage', 0);
    }

    /** @test */
    public function it_regenerates_narration_when_the_stored_file_has_gone_missing()
    {
        $this->fakeMediaDisk();

        Http::fake([
            'api.elevenlabs.io/*' => Http::response('regenerated-mp3', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $storedPath = "stories/{$story->id}/pages/1/narration.mp3";

        // The row claims we have narration, but the file isn't on this disk —
        // e.g. the disk was switched out from under us.
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'content' => 'Once upon a time.',
            'audio_url' => route('stories.pages.audio', ['story' => $story->id, 'pageNumber' => 1]),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/1/audio");

        $response->assertOk();
        $this->assertSame('regenerated-mp3', $response->getContent());
        Storage::disk('media')->assertExists($storedPath);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.elevenlabs.io/v1/text-to-speech/'));
    }

    /** @test */
    public function it_returns_403_for_another_users_story()
    {
        Http::fake();

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $owner->id]);
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'audio_url' => null,
        ]);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/stories/{$story->id}/pages/1/audio")
            ->assertForbidden();

        Http::assertNothingSent();
    }

    /** @test */
    public function it_returns_404_for_nonexistent_page_number()
    {
        Http::fake();

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/99/audio")
            ->assertNotFound();

        Http::assertNothingSent();
    }

    /** @test */
    public function it_returns_422_for_a_page_with_no_text_to_narrate()
    {
        Http::fake();

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'content' => '   ',
            'audio_url' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/1/audio")
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_returns_429_when_the_daily_character_cap_is_reached()
    {
        Http::fake();

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'content' => 'A short page.',
            'audio_url' => null,
        ]);

        // Burn the whole daily allowance before asking for narration.
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => ElevenLabsUsage::getDailyLimit($user->id),
            'voice_id' => 'test-voice',
            'model_id' => config('services.elevenlabs.default_model'),
            'estimated_cost' => 0,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/1/audio")
            ->assertStatus(429);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_requires_authentication()
    {
        $story = Story::factory()->create();
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
        ]);

        $this->postJson("/api/v1/stories/{$story->id}/pages/1/audio")
            ->assertUnauthorized();
    }
}
