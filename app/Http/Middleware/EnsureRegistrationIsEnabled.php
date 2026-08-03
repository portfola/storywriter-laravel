<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes both signup paths when config('services.auth.registration_enabled')
 * is false: the JSON endpoint the apps use, and the Breeze web form.
 *
 * 403 rather than 404, because the route genuinely exists and hiding it would
 * only make a closed environment look broken. Existing accounts still log in.
 */
class EnsureRegistrationIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('services.auth.registration_enabled')) {
            return $next($request);
        }

        $message = 'Registration is closed on this environment.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
