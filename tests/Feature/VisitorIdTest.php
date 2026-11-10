<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureVisitorId;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class VisitorIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_visitor', fn () => response(request()->attributes->get('visitor_id')));
    }

    private function visitorCookie($response): ?Cookie
    {
        return collect($response->headers->getCookies())->first(fn (Cookie $c) => $c->getName() === EnsureVisitorId::COOKIE);
    }

    public function test_a_new_visitor_gets_an_http_only_uuid_cookie(): void
    {
        $response = $this->get('/_visitor');
        $cookie = $this->visitorCookie($response);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertTrue(Str::isUuid($response->getContent()));
    }

    public function test_a_valid_cookie_is_reused_and_not_reissued(): void
    {
        $id = (string) Str::uuid();

        $response = $this->withCookie(EnsureVisitorId::COOKIE, $id)->get('/_visitor');

        $response->assertContent($id);
        $this->assertNull($this->visitorCookie($response));
    }

    public function test_an_invalid_cookie_value_is_replaced(): void
    {
        $response = $this->withCookie(EnsureVisitorId::COOKIE, 'not-a-uuid')->get('/_visitor');

        $this->assertNotNull($this->visitorCookie($response));
        $this->assertTrue(Str::isUuid($response->getContent()));
    }

    public function test_the_home_page_also_issues_the_cookie(): void
    {
        $this->withoutVite();

        $this->assertNotNull($this->visitorCookie($this->get('/')));
    }
}
