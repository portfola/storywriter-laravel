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
        'default_voice_id' => env('ELEVENLABS_DEFAULT_VOICE_ID', '56AoDkrOh6qfVPDXZ7Pt'),
        'default_model' => env('ELEVENLABS_DEFAULT_MODEL', 'eleven_flash_v2_5'),
        'timeout' => env('ELEVENLABS_TIMEOUT', 30),
        'base_url' => 'https://api.elevenlabs.io/v1',

        // Daily usage limits (characters per user per day)
        'daily_limit_free' => env('ELEVENLABS_DAILY_LIMIT_FREE', 10000),
        'daily_limit_paid' => env('ELEVENLABS_DAILY_LIMIT_PAID', 50000),
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
    ],

];
