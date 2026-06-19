<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs Text-to-Speech
    |--------------------------------------------------------------------------
    |
    | API key for ElevenLabs TTS service. In staging/production, this is
    | loaded from AWS SSM Parameter Store. Falls back to .env for local dev.
    |
    */

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'agent_id' => env('ELEVENLABS_AGENT_ID'),
        'default_voice_id' => env('ELEVENLABS_DEFAULT_VOICE_ID', '56AoDkrOh6qfVPDXZ7Pt'),
        'default_model' => env('ELEVENLABS_DEFAULT_MODEL', 'eleven_flash_v2_5'),
        'timeout' => env('ELEVENLABS_TIMEOUT', 30),
        'base_url' => 'https://api.elevenlabs.io/v1',

        // Daily usage limits (characters per user per day)
        'daily_limit_free' => env('ELEVENLABS_DAILY_LIMIT_FREE', 10000),
        'daily_limit_paid' => env('ELEVENLABS_DAILY_LIMIT_PAID', 50000),

        // Per-minute request-rate ceilings (see AppServiceProvider rate limiters)
        'rate_limit_per_minute' => (int) env('ELEVENLABS_RATE_LIMIT_PER_MINUTE', 30),
        'credentials_rate_limit_per_minute' => (int) env('ELEVENLABS_CREDENTIALS_RATE_LIMIT_PER_MINUTE', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Together AI
    |--------------------------------------------------------------------------
    |
    | API key for Together AI (LLM and image generation). In staging/production,
    | this is loaded from AWS SSM Parameter Store. Falls back to .env for local dev.
    |
    */

    'together' => [
        'api_key' => env('TOGETHER_API_KEY'),
        'text_model' => env('TOGETHER_TEXT_MODEL', 'moonshotai/Kimi-K2.5'),
        'image_model' => env('TOGETHER_IMAGE_MODEL', 'google/flash-image-3.1'),
        'image_width' => (int) env('TOGETHER_IMAGE_WIDTH', 1024),
        'image_height' => (int) env('TOGETHER_IMAGE_HEIGHT', 768),
        'image_steps' => (int) env('TOGETHER_IMAGE_STEPS', 4),

        // Per-minute request-rate ceiling (see AppServiceProvider rate limiters)
        'rate_limit_per_minute' => (int) env('TOGETHER_RATE_LIMIT_PER_MINUTE', 10),

        // Daily generation caps per user (free tier). Story text and images are
        // counted separately since they are distinct cost units.
        'daily_story_limit_free' => (int) env('TOGETHER_DAILY_STORY_LIMIT_FREE', 25),
        'daily_image_limit_free' => (int) env('TOGETHER_DAILY_IMAGE_LIMIT_FREE', 75),

        // Rough cost estimates (USD) used only for reporting.
        'cost_per_story' => (float) env('TOGETHER_COST_PER_STORY', 0.002),
        'cost_per_image' => (float) env('TOGETHER_COST_PER_IMAGE', 0.003),
    ],

    /*
    |--------------------------------------------------------------------------
    | PostHog Product Analytics
    |--------------------------------------------------------------------------
    |
    | API key and host for PostHog event tracking. In staging/production,
    | loaded from AWS SSM Parameter Store. Falls back to .env for local dev.
    |
    */

    'posthog' => [
        'api_key' => env('POSTHOG_API_KEY'),
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),

        // Analytics is on in production automatically. Set POSTHOG_FORCE_ENABLE=true
        // to also send events from local/staging for testing. Events are stamped
        // with an `environment` property so dev traffic can be filtered out in the
        // PostHog dashboard.
        'enabled' => (bool) env('POSTHOG_API_KEY')
            && (env('APP_ENV') === 'production'
                || filter_var(env('POSTHOG_FORCE_ENABLE', false), FILTER_VALIDATE_BOOLEAN)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq Console API
    |--------------------------------------------------------------------------
    |
    | API key for Groq Console. Used by Heirloom's NarrativeService for
    | transcript-to-narrative synthesis (llama-3.3-70b-versatile).
    |
    */

    'groq' => [
        'key' => env('GROQ_API_KEY'),
    ],

];
