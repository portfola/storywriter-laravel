<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

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

    /**
     * An inline event handler attribute: onclick, onsubmit, onerror and the rest.
     *
     * Anchored on the whitespace before the attribute name so that Alpine's
     * `x-on:click` and `@click` bindings do not match — those are evaluated by
     * Alpine rather than by the browser's attribute parser, so the policy has no
     * quarrel with them.
     */
    private const HANDLER_ATTRIBUTE = '/\son[a-z]+\s*=/i';

    /**
     * A throwaway public directory for the duration of one test.
     */
    private string $publicPath;

    /**
     * Point public_path() somewhere disposable.
     *
     * The middleware reads public/hot to decide whether the Vite dev server is
     * up, and `composer dev` owns the real one. A suite run alongside it would
     * find a hot file where the tests below expect none, and the teardown would
     * delete the file the running dev server needs. So the tests get their own
     * public directory and never read or write the repository's.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->publicPath = sys_get_temp_dir().'/storywriter-csp-'.uniqid();

        mkdir($this->publicPath);

        $this->app->usePublicPath($this->publicPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->publicPath.'/hot');
        @rmdir($this->publicPath);

        parent::tearDown();
    }

    /**
     * One directive's value, looked up by name.
     *
     * Substring assertions are no use for the question "does script-src allow
     * 'unsafe-inline'": the token is still a legitimate part of style-src and of
     * script-src-attr, so a search of the whole header finds it either way.
     *
     * @return list<string> the directive's source list, empty if it is absent
     */
    private function directive(string $name, string $uri = '/'): array
    {
        $csp = (string) $this->get($uri)->headers->get('Content-Security-Policy');

        foreach (explode(';', $csp) as $directive) {
            $parts = preg_split('/\s+/', trim($directive), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (array_shift($parts) === $name) {
                return array_values($parts);
            }
        }

        return [];
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
            "script-src 'self' 'unsafe-eval'",
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

    public function test_script_src_does_not_allow_inline_scripts(): void
    {
        // The whole point of the policy on a rendered page. With 'unsafe-inline'
        // here an injected <script> block runs and the header is decoration.
        $this->assertNotContains("'unsafe-inline'", $this->directive('script-src'));
        $this->assertContains("'unsafe-eval'", $this->directive('script-src'));
    }

    public function test_inline_event_handler_attributes_are_not_allowed(): void
    {
        // No script-src-attr exception any more: the seven handler attributes it
        // was written for are gone. Logout is a submit button and the Heirloom
        // delete confirmations are Alpine, so nothing rendered needs one. An
        // empty list is what the helper returns for an absent directive, which
        // is the assertion -- with the directive back, handler attributes run
        // again and an injected onerror is a way in.
        $this->assertSame([], $this->directive('script-src-attr'));
    }

    public function test_no_rendered_page_carries_an_inline_script_block(): void
    {
        // What makes 'unsafe-inline' safe to leave out of script-src. If a view
        // ever grows a <script> block, that page breaks under this policy -- and
        // this test is the one that says so, rather than the browser console.
        //
        // Needs the real public directory: every layout here calls @vite, which
        // reads the build manifest, and setUp() pointed that lookup at an empty
        // temporary directory. This test only reads.
        $this->app->usePublicPath(base_path('public'));

        foreach ($this->renderedPages() as $uri => $html) {
            preg_match_all('/<script\b[^>]*>/i', $html, $matches);

            $inline = array_filter($matches[0], fn ($tag) => ! str_contains($tag, 'src='));

            $this->assertSame(
                [],
                array_values($inline),
                "$uri has an inline <script> block, which script-src now blocks."
            );
        }
    }

    public function test_no_rendered_page_carries_an_inline_event_handler_attribute(): void
    {
        // The other half: script-src-attr is gone, so an onclick or onsubmit is
        // now dead markup. Nothing throws when the browser refuses one -- the
        // button simply does nothing -- so this is the only thing that notices.
        $this->app->usePublicPath(base_path('public'));

        foreach ($this->renderedPages() as $uri => $html) {
            $this->assertDoesNotMatchRegularExpression(
                self::HANDLER_ATTRIBUTE,
                $html,
                "$uri has an inline event handler attribute, which the policy now blocks."
            );
        }
    }

    public function test_no_blade_view_declares_an_inline_event_handler_attribute(): void
    {
        // The rendered-page check above can only see pages a test can reach. The
        // Heirloom views need a subject, a session and a narrative to show their
        // delete buttons at all, and there are no factories for those yet, so
        // the handler attributes that used to live there would render clean in a
        // test and broken in the browser. Read the source instead: it covers
        // every view, whatever it takes to reach one.
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        $offenders = [];

        foreach ($views as $view) {
            if (! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($view->getPathname());

            if (preg_match(self::HANDLER_ATTRIBUTE, $source)) {
                $offenders[] = str_replace(base_path().'/', '', $view->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views carry an inline event handler attribute, which the policy blocks. '
            .'Use an Alpine x-on/@ binding, or a plain submit button.'
        );
    }

    /**
     * Every page a test can render, keyed by URI.
     *
     * @return array<string, string> URI => rendered HTML
     */
    private function renderedPages(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $pages = [
            '/' => null,
            '/login' => null,
            '/dashboard' => $admin,
            '/dashboard/analytics' => $admin,
            '/profile' => $admin,
            '/heirloom/dashboard' => $admin,
            '/heirloom/sessions' => $admin,
            '/heirloom/narratives' => $admin,
        ];

        $rendered = [];

        foreach ($pages as $uri => $actAs) {
            $response = $actAs ? $this->actingAs($actAs)->get($uri) : $this->get($uri);

            $response->assertOk();

            $rendered[$uri] = (string) $response->getContent();
        }

        return $rendered;
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

    public function test_a_stray_hot_file_is_never_read_outside_local_and_testing(): void
    {
        // Staging and production never run a dev server, so the file has no
        // business widening their policy if one ever lands in the deployed
        // public directory.
        file_put_contents(public_path('hot'), 'http://localhost:5173');

        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);

            $this->assertNotContains(
                'http://localhost:5173',
                $this->directive('script-src'),
                "The hot file widened script-src in the $environment environment."
            );
        }
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
