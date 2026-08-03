<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureRegistrationIsEnabled;
use App\Http\Middleware\LogStoryActivity;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // The security headers matter most on the pages a browser actually
        // renders — the dashboard, login and profile — so the web group is the
        // one that needs them. They stay on the API group too: they do little
        // for a JSON response, but they cost nothing and one policy everywhere
        // is easier to reason about than two.
        $middleware->web(append: [
            SetSecurityHeaders::class,
        ]);

        $middleware->api(append: [
            SetSecurityHeaders::class,
        ]);

        // CORS is handled by the HandleCors middleware already present in
        // Laravel's default global stack, which reads config/cors.php.
        // Allowed origins belong there — not here.

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'log.story' => LogStoryActivity::class,
            'registration.enabled' => EnsureRegistrationIsEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
