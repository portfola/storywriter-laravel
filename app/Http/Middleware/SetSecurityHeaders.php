<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * Content Security Policy directives, one per entry.
     *
     * These are joined into a single-line header value. A header value may not
     * contain a newline — PHP's header() warns and drops the header if it does,
     * and Laravel promotes that warning to an ErrorException. It fires at send()
     * time, after the response has already been built, so it escapes the normal
     * error handling and turns every otherwise-successful API response into a
     * bare 500.
     *
     * @var list<string>
     */
    protected const CSP_DIRECTIVES = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: https:",
        "font-src 'self'",
        "connect-src 'self' https://api.together.ai https://api.elevenlabs.io",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Content Security Policy - restrictive for security
        $response->header('Content-Security-Policy', implode('; ', self::CSP_DIRECTIVES));

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
}
