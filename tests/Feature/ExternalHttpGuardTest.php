<?php

namespace Tests\Feature;

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalHttpGuardTest extends TestCase
{
    public function test_unfaked_outbound_requests_are_blocked(): void
    {
        $this->expectException(StrayRequestException::class);

        Http::get('https://generativelanguage.googleapis.com/v1beta/models');
    }

    public function test_faked_outbound_requests_are_allowed(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['ok' => true])]);

        $this->assertTrue(Http::get('https://generativelanguage.googleapis.com/v1beta/models')->json('ok'));
    }
}
