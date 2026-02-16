<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogStoryActivity
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Execute the request
        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000); // milliseconds

        try {
            // Only log for story generation endpoint
            if (! $request->is('api/*/stories/generate')) {
                return $response;
            }

            // Grab everything the user sent, but remove sensitive stuff
            $inputs = $request->except(['password', 'token', 'api_key', 'authorization']);

            // Enrich the inputs with computed data for easier querying
            $enrichedInputs = $inputs;

            if (isset($inputs['transcript'])) {
                $enrichedInputs['transcript_length'] = strlen($inputs['transcript']);
                $enrichedInputs['transcript_word_count'] = str_word_count($inputs['transcript']);
                $enrichedInputs['user_turns'] = substr_count($inputs['transcript'], 'User:');
            }

            // Add response data if successful
            $enrichedInputs['response_status'] = $response->status();
            $enrichedInputs['generation_successful'] = $response->isSuccessful();

            if ($response->isSuccessful() && method_exists($response, 'getData')) {
                $responseData = $response->getData(true);
                if (isset($responseData['data']['story'])) {
                    $story = $responseData['data']['story'];
                    $enrichedInputs['story_length'] = strlen($story);
                    $enrichedInputs['story_word_count'] = str_word_count($story);
                    $enrichedInputs['pages_generated'] = substr_count($story, '---PAGE BREAK---') + 1;
                }
            }

            // Add device type
            $userAgent = $request->header('User-Agent', '');
            $enrichedInputs['device_type'] = $this->detectDeviceType($userAgent);

            DB::table('story_analytics')->insert([
                'user_id' => Auth::id(),
                'story_inputs' => json_encode($enrichedInputs),
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent,
                'generation_time_ms' => $duration,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            // Log the error but don't break the response
            Log::error('Failed to log story activity', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        if (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/ipad|tablet/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }
}
