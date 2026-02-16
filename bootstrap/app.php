<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class, [
            'paths' => ['api/*', 'sanctum/csrf-cookie'],
            'allowed_methods' => ['*'],
            'allowed_origins' => [
                'https://staging.storywriter.net',      // Staging frontend
                'https://storywriter.net',               // Production frontend
                'https://www.storywriter.net',           // Production www subdomain
                'http://localhost:3000',                 // Local web development
                'http://localhost:8081',                 // Expo development
                'http://localhost:19006',                // Expo web
                'http://127.0.0.1:8081',                 // Local alternative
            ],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => true,              // Required for Authorization header
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'log.story' => \App\Http\Middleware\LogStoryActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
