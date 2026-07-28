<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * Where the web font comes from.
     *
     * The Blade layouts pull a stylesheet from Bunny Fonts and the font files it
     * points at, so both style-src and font-src have to name the host. Leave it
     * out and the dashboard still renders, just in a fallback typeface, with the
     * browser console full of blocked-resource complaints.
     */
    protected const FONT_HOST = 'https://fonts.bunny.net';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Content Security Policy - restrictive for security
        $response->header('Content-Security-Policy', implode('; ', $this->cspDirectives()));

        // Prevent clickjacking
        $response->header('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection
        $response->header('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }

    /**
     * Content Security Policy directives, one per entry.
     *
     * These are joined into a single-line header value. A header value may not
     * contain a newline — PHP's header() warns and drops the header if it does,
     * and Laravel promotes that warning to an ErrorException. It fires at send()
     * time, after the response has already been built, so it escapes the normal
     * error handling and turns every otherwise-successful response into a bare
     * 500. Nothing that goes in here may span more than one line.
     *
     * @return list<string>
     */
    protected function cspDirectives(): array
    {
        $viteOrigins = $this->viteDevServerOrigins();

        $scriptSrc = ["'self'", "'unsafe-inline'", "'unsafe-eval'", ...$viteOrigins['http']];
        $styleSrc = ["'self'", "'unsafe-inline'", self::FONT_HOST, ...$viteOrigins['http']];
        $connectSrc = [
            "'self'",
            'https://api.together.ai',
            'https://api.elevenlabs.io',
            ...$viteOrigins['http'],
            ...$viteOrigins['ws'],
        ];

        return [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            "img-src 'self' data: https:",
            'font-src '.implode(' ', ["'self'", self::FONT_HOST]),
            // Page narration is served from the media bucket over a signed URL,
            // so the <audio> tags on the dashboard's story page are cross-origin.
            // Without this they fall back to default-src and never play.
            "media-src 'self' https:",
            'connect-src '.implode(' ', $connectSrc),
            "frame-ancestors 'none'",
        ];
    }

    /**
     * The Vite dev server's origins, while it is running.
     *
     * `composer dev` serves the dashboard's CSS and JS off a separate port and
     * opens a websocket back to it for hot reload. Neither is 'self', so under
     * the production policy a local dashboard loses all its styling. Laravel
     * writes the dev server's URL to public/hot and deletes the file when the
     * server stops, so this widens nothing on staging or production.
     *
     * The value is checked against a strict pattern before it goes anywhere near
     * a header — a stray newline in that file would otherwise 500 every response.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    protected function viteDevServerOrigins(): array
    {
        $none = ['http' => [], 'ws' => []];
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return $none;
        }

        $origin = rtrim(trim((string) file_get_contents($hotFile)), '/');

        if (! preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?$#', $origin)) {
            return $none;
        }

        return [
            'http' => [$origin],
            'ws' => [preg_replace('#^http#', 'ws', $origin)],
        ];
    }
}
