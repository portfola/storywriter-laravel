<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

/**
 * SetSecurityHeadersTest
 *
 * Guards the security headers appended to every API response.
 *
 * The important assertion here is that no header value contains a newline.
 * PHP's header() refuses a value spanning more than one line — it warns, and
 * Laravel turns that warning into an ErrorException, raised inside
 * Response::sendHeaders() after the middleware stack has finished and the
 * response has been built. Laravel's test client never calls send(), so a
 * status-code assertion cannot see this: it only surfaces over a real HTTP
 * connection, where it turns every successful API response into a 500. Assert
 * on the header value itself.
 */
class SetSecurityHeadersTest extends TestCase
{
    public function test_api_responses_carry_the_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        foreach ([
            'Content-Security-Policy',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'X-XSS-Protection',
            'Referrer-Policy',
            'Permissions-Policy',
        ] as $header) {
            $this->assertNotNull(
                $response->headers->get($header),
                "Expected the $header header on an API response."
            );
        }
    }

    public function test_no_security_header_value_spans_multiple_lines(): void
    {
        $response = $this->getJson('/api/v1/health');

        foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
                $this->assertDoesNotMatchRegularExpression(
                    '/[\r\n]/',
                    (string) $value,
                    "Header $name contains a newline; PHP will reject it at send() time."
                );
            }
        }
    }

    public function test_content_security_policy_keeps_its_directives(): void
    {
        $csp = $this->getJson('/api/v1/health')->headers->get('Content-Security-Policy');

        foreach ([
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self' https://api.together.ai https://api.elevenlabs.io",
            "frame-ancestors 'none'",
        ] as $directive) {
            $this->assertStringContainsString($directive, (string) $csp);
        }
    }
}
