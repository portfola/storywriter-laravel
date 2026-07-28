<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

/**
 * SetSecurityHeadersTest
 *
 * Guards the security headers appended to every response — API and web alike.
 *
 * The important assertion here is that no header value contains a newline.
 * PHP's header() refuses a value spanning more than one line — it warns, and
 * Laravel turns that warning into an ErrorException, raised inside
 * Response::sendHeaders() after the middleware stack has finished and the
 * response has been built. Laravel's test client never calls send(), so a
 * status-code assertion cannot see this: it only surfaces over a real HTTP
 * connection, where it turns every successful response into a 500. Assert on
 * the header value itself.
 */
class SetSecurityHeadersTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const EXPECTED_HEADERS = [
        'Content-Security-Policy',
        'X-Frame-Options',
        'X-Content-Type-Options',
        'X-XSS-Protection',
        'Referrer-Policy',
        'Permissions-Policy',
    ];

    protected function tearDown(): void
    {
        @unlink(public_path('hot'));

        parent::tearDown();
    }

    public function test_api_responses_carry_the_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        foreach (self::EXPECTED_HEADERS as $header) {
            $this->assertNotNull(
                $response->headers->get($header),
                "Expected the $header header on an API response."
            );
        }
    }

    public function test_web_pages_carry_the_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        foreach (self::EXPECTED_HEADERS as $header) {
            $this->assertNotNull(
                $response->headers->get($header),
                "Expected the $header header on a web page."
            );
        }
    }

    public function test_no_security_header_value_spans_multiple_lines(): void
    {
        foreach (['/api/v1/health', '/'] as $uri) {
            $response = $this->get($uri);

            foreach ($response->headers->all() as $name => $values) {
                foreach ($values as $value) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/[\r\n]/',
                        (string) $value,
                        "Header $name on $uri contains a newline; PHP will reject it at send() time."
                    );
                }
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

    public function test_content_security_policy_allows_the_font_host_the_layouts_use(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("font-src 'self' https://fonts.bunny.net", $csp);
    }

    public function test_content_security_policy_allows_signed_media(): void
    {
        // Narration audio on the dashboard's story page comes off the media
        // bucket over a signed URL, so it is cross-origin.
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("media-src 'self' https:", $csp);
    }

    public function test_the_vite_dev_server_is_allowed_only_while_it_is_running(): void
    {
        $withoutHotFile = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('localhost:5173', $withoutHotFile);

        file_put_contents(public_path('hot'), "http://localhost:5173\n");

        $withHotFile = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('http://localhost:5173', $withHotFile);
        $this->assertStringContainsString('ws://localhost:5173', $withHotFile);
    }

    public function test_a_junk_hot_file_is_ignored_rather_than_written_into_the_header(): void
    {
        // A newline reaching the header value is what turned every response into
        // a 500 in #83, so anything that does not look like an origin is dropped.
        file_put_contents(public_path('hot'), "http://localhost:5173\r\nX-Injected: yes");

        $response = $this->get('/');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('localhost:5173', $csp);
        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $csp);
        $this->assertNull($response->headers->get('X-Injected'));
    }
}
