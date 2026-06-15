<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
    }
}
