<?php

namespace App\Support;

use PostHog\PostHog;

class Analytics
{
    /**
     * Capture a PostHog event when analytics is enabled for the current
     * environment (production, or any environment with POSTHOG_FORCE_ENABLE=true).
     *
     * Every event is automatically stamped with an `environment` property so
     * dev/staging traffic can be filtered out from production in the dashboard.
     */
    public static function capture(string $distinctId, string $event, array $properties = []): void
    {
        if (! config('services.posthog.enabled')) {
            return;
        }

        PostHog::capture([
            'distinctId' => $distinctId,
            'event' => $event,
            'properties' => array_merge($properties, [
                'environment' => app()->environment(),
            ]),
        ]);
    }
}
