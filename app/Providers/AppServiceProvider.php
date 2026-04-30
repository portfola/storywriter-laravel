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
        // Flush PostHog events when the application terminates (production only)
        $this->app->terminating(function () {
            if ($this->app->environment('production') && config('services.posthog.api_key')) {
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

        // Initialize PostHog in production only
        $posthogKey = config('services.posthog.api_key');
        if ($this->app->environment('production') && $posthogKey) {
            PostHog::init($posthogKey, [
                'host' => config('services.posthog.host', 'https://us.i.posthog.com'),
            ]);
        }
    }
}
