<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AWS SSM Parameter Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for loading secrets from AWS Systems Manager Parameter
    | Store. Parameters are loaded at boot time and cached to avoid excessive
    | AWS API calls.
    |
    */

    'enabled' => env('AWS_SSM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Parameter Store Path Prefix
    |--------------------------------------------------------------------------
    |
    | The path prefix for parameters in SSM. This is environment-specific:
    | - staging:    /storywriter/staging/
    | - production: /storywriter/production/
    |
    */

    'path_prefix' => env('AWS_SSM_PATH_PREFIX', '/storywriter/'.env('APP_ENV', 'local').'/'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache parameters (in seconds). Default is 5 minutes (300s).
    | Set to 0 to disable caching.
    |
    */

    'cache_ttl' => env('AWS_SSM_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | AWS Region
    |--------------------------------------------------------------------------
    |
    | The AWS region where your Parameter Store parameters are stored.
    |
    */

    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    /*
    |--------------------------------------------------------------------------
    | Parameters to Load
    |--------------------------------------------------------------------------
    |
    | Define the parameters to load from SSM. The key is the config key path,
    | and the value is the parameter name in SSM (without the path prefix).
    |
    | Example: 'services.elevenlabs.api_key' => 'ELEVENLABS_API_KEY'
    | This loads /storywriter/{env}/ELEVENLABS_API_KEY into config('services.elevenlabs.api_key')
    |
    */

    'parameters' => [
        'services.elevenlabs.api_key' => 'ELEVENLABS_API_KEY',
        'services.together.api_key' => 'TOGETHER_API_KEY',
    ],

];
