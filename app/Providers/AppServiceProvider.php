<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PostHog\PostHog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Flush PostHog events when the application terminates
        $this->app->terminating(function () {
            if (config('services.posthog.enabled')) {
                PostHog::flush();
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Initialize PostHog when analytics is enabled (production, or any
        // environment with POSTHOG_FORCE_ENABLE=true)
        if (config('services.posthog.enabled')) {
            PostHog::init(config('services.posthog.api_key'), [
                'host' => config('services.posthog.host', 'https://us.i.posthog.com'),
            ]);
        }

        $this->configureRateLimiters();
    }

    /**
     * Define named rate limiters for the AI-backed endpoints.
     *
     * These cap the request *rate* (a first line of defence against a script
     * hammering an endpoint). Per-user daily spend caps are enforced separately
     * in the controllers via the usage models. All limiters key on the
     * authenticated user, falling back to IP for any unauthenticated edge case.
     */
    protected function configureRateLimiters(): void
    {
        // Story + page image generation (Together AI). Generation is heavy and
        // slow, so the per-minute ceiling is deliberately low.
        RateLimiter::for('ai-generation', function (Request $request) {
            return Limit::perMinute((int) config('services.together.rate_limit_per_minute', 10))
                ->by($request->user()?->id ?: $request->ip());
        });

        // Signed-URL issuance for ElevenLabs conversations. Each issued URL is
        // uncapped spend on a direct client->ElevenLabs WebSocket we no longer
        // see, so this is throttled hardest.
        RateLimiter::for('conversation-credentials', function (Request $request) {
            return Limit::perMinute((int) config('services.elevenlabs.credentials_rate_limit_per_minute', 5))
                ->by($request->user()?->id ?: $request->ip());
        });

        // Other ElevenLabs proxy/TTS/voices endpoints.
        RateLimiter::for('conversation', function (Request $request) {
            return Limit::perMinute((int) config('services.elevenlabs.rate_limit_per_minute', 30))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
