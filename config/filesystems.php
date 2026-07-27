<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Media URL Lifetime
    |--------------------------------------------------------------------------
    |
    | How long a signed URL for a story illustration stays valid, in minutes.
    | It is generous on purpose: nothing in the app notices an image URL going
    | stale, so a child who leaves a storybook open would just watch the picture
    | vanish. A day comfortably outlives any one sitting, and a signed URL points
    | at a single file and can only read it, so it is a mild thing to hand out.
    | MEDIA_URL_TTL_MINUTES shortens it without a code change.
    |
    */

    'media_url_ttl_minutes' => env('MEDIA_URL_TTL_MINUTES', 1440),

    /*
    |--------------------------------------------------------------------------
    | Media URL Stability Window
    |--------------------------------------------------------------------------
    |
    | A signed URL carries the moment it was signed, so asking for the same
    | picture twice normally hands back two different URLs. The tablet app has
    | no way to tell they point at the same file, so it re-downloads a cover it
    | already has every time the bookshelf regains focus.
    |
    | Signing is pinned to the start of a window this many minutes long instead.
    | Every request inside one window gets a byte-identical URL, so ordinary
    | HTTP and image caching does its job. Longer windows cache better; shorter
    | ones keep the signature nearer to real time. Set it to 0 to sign at the
    | current instant, which is the old behaviour.
    |
    */

    'media_url_window_minutes' => env('MEDIA_URL_WINDOW_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
