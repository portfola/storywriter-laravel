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

        // Heirloom runs in a browser, so its requests carry an Origin that
        // matches Sanctum's stateful list (localhost:3000 by default). That
        // sends them through the session + CSRF stack, and a write request
        // fails with 419 before the controller runs. Both apps authenticate
        // with bearer tokens and hold no session, so CSRF protects nothing on
        // these paths: login is what issues the token, and the Heirloom API is
        // token-only behind auth:sanctum.
        $middleware->validateCsrfTokens(except: [
            'api/auth/login',
            'api/v1/auth/login',
            'api/heirloom/v1/*',
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
