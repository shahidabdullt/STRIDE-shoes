<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fakes the Gemini Interactions endpoint. Responses are real shapes captured in Phase 0
 * (tests/Fixtures/gemini) or built with the same structure.
 */
trait FakesGemini
{
    protected const INTERACTIONS_URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    /** Queue responses for consecutive Interactions calls. Each item is [status, body] or a closure. */
    protected function fakeGemini(array $responses): void
    {
        config(['services.gemini.key' => 'test-key-not-real']);

        $sequence = Http::sequence();
        foreach ($responses as $response) {
            $response instanceof \Closure
                ? $sequence->pushResponse($response())
                : $sequence->push($response[1], $response[0]);
        }

        Http::fake([self::INTERACTIONS_URL => $sequence]);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    protected function geminiFixture(string $name): array
    {
        $json = json_decode(file_get_contents(base_path("tests/Fixtures/gemini/{$name}.json")), true);
        $response = $json['response'] ?? $json;

        return [$response['status'], $response['body']];
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    protected function geminiText(string $text, string $status = 'completed'): array
    {
        return [200, [
            'status' => $status,
            'usage' => ['total_input_tokens' => 100, 'total_output_tokens' => 20, 'total_thought_tokens' => 10],
            'steps' => [
                ['type' => 'thought', 'signature' => 'sig'],
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ]];
    }

    /**
     * @param  list<array{0: string, 1: mixed}>  $calls  [name, arguments] pairs
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function geminiCalls(array $calls): array
    {
        $steps = [['type' => 'thought', 'signature' => 'sig']];
        foreach ($calls as $i => [$name, $arguments]) {
            $steps[] = ['type' => 'function_call', 'id' => 'call_'.($i + 1).'_'.$name, 'name' => $name, 'arguments' => $arguments];
        }

        return [200, [
            'status' => 'requires_action',
            'usage' => ['total_input_tokens' => 80, 'total_output_tokens' => 10, 'total_thought_tokens' => 5],
            'steps' => $steps,
        ]];
    }

    /** @return list<array<string, mixed>> Decoded bodies of every Interactions request sent so far. */
    protected function sentGeminiBodies(): array
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::INTERACTIONS_URL)
            ->map(fn (array $pair) => $pair[0]->data())->values()->all();
    }
}
