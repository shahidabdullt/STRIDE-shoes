<?php

namespace Tests\Feature\Assistant;

use App\Services\Gemini\GeminiException;
use App\Services\Gemini\GeminiTextClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesGemini;
use Tests\TestCase;

class GeminiTextClientTest extends TestCase
{
    use FakesGemini;

    private function createInteraction(): array
    {
        return app(GeminiTextClient::class)->createInteraction(
            ['model' => 'gemini-3.6-flash', 'input' => [['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'Hi']]]]],
            microtime(true) + 20,
        );
    }

    public function test_it_sends_a_stateless_request_with_the_key_in_a_header(): void
    {
        $this->fakeGemini([$this->geminiFixture('s4_36flash_tool_round3')]);

        $result = $this->createInteraction();

        $this->assertSame('completed', $result['status']);
        Http::assertSent(function (Request $request) {
            return $request->url() === self::INTERACTIONS_URL
                && $request->hasHeader('x-goog-api-key', 'test-key-not-real')
                && $request['store'] === false
                && $request['model'] === 'gemini-3.6-flash'
                && ! str_contains($request->url(), 'key=');
        });
    }

    public function test_a_503_is_retried_once_then_succeeds(): void
    {
        $this->fakeGemini([$this->geminiFixture('s4_error_503_high_demand'), $this->geminiText('Hello')]);

        $this->assertSame('completed', $this->createInteraction()['status']);
        Http::assertSentCount(2);
    }

    public function test_a_429_is_retried_once_and_then_reported(): void
    {
        $this->fakeGemini([[429, ['error' => ['message' => 'quota', 'code' => 'resource_exhausted']]], [429, ['error' => ['message' => 'quota']]]]);

        try {
            $this->createInteraction();
            $this->fail('Expected an exception');
        } catch (GeminiException $e) {
            $this->assertSame('rate_limited', $e->kind);
        }
        Http::assertSentCount(2);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function errorFixtures(): array
    {
        return [
            'bad key (array body)' => ['s4_error_bad_key', 'auth'],
            'unknown model (string code)' => ['s4_error_bad_model', 'not_found'],
        ];
    }

    #[DataProvider('errorFixtures')]
    public function test_non_retryable_errors_map_to_a_kind_without_retrying(string $fixture, string $kind): void
    {
        $this->fakeGemini([$this->geminiFixture($fixture), $this->geminiText('should not be used')]);

        try {
            $this->createInteraction();
            $this->fail('Expected an exception');
        } catch (GeminiException $e) {
            $this->assertSame($kind, $e->kind);
        }
        Http::assertSentCount(1);
    }

    public function test_a_timeout_becomes_a_timeout_error(): void
    {
        config(['services.gemini.key' => 'test-key-not-real']);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        try {
            $this->createInteraction();
            $this->fail('Expected an exception');
        } catch (GeminiException $e) {
            $this->assertSame('timeout', $e->kind);
        }
    }

    public function test_a_missing_key_fails_without_calling_out(): void
    {
        config(['services.gemini.key' => '']);
        Http::fake();

        try {
            $this->createInteraction();
            $this->fail('Expected an exception');
        } catch (GeminiException $e) {
            $this->assertSame('not_configured', $e->kind);
        }
        Http::assertNothingSent();
    }

    public function test_an_unexpected_success_body_is_rejected(): void
    {
        $this->fakeGemini([[200, ['hello' => 'world']]]);

        $this->expectException(GeminiException::class);
        $this->createInteraction();
    }
}
