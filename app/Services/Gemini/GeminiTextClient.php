<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Gemini Interactions API over Laravel's HTTP client, in stateless mode (store=false).
 *
 * Shapes verified against the live API in Phase 0 (spikes/s4_text.php) and on 8 Nov 2026:
 * input steps user_input / model_output / function_call / function_result, generation_config
 * {temperature, max_output_tokens}, statuses completed / requires_action / incomplete.
 */
class GeminiTextClient
{
    /**
     * One create call, retried once on 429 / 5xx / timeout while time remains before the deadline.
     *
     * @param  array<string, mixed>  $body  Interactions request without `store` (always false here).
     * @param  float  $deadline  Absolute microtime(true) by which the call must have finished.
     * @return array<string, mixed> The decoded interaction.
     *
     * @throws GeminiException
     */
    public function createInteraction(array $body, float $deadline): array
    {
        $key = (string) config('services.gemini.key');

        if ($key === '') {
            throw new GeminiException('not_configured', 'GEMINI_API_KEY is not set.');
        }

        $body = ['store' => false, ...$body];
        $attempt = 0;

        while (true) {
            $attempt++;
            $remaining = $deadline - microtime(true);

            if ($remaining < 1) {
                throw new GeminiException('timeout', 'No time left before the turn deadline.');
            }

            try {
                return $this->send($key, $body, min($remaining, (float) config('services.gemini.timeout')));
            } catch (GeminiException $e) {
                if ($attempt >= 2 || ! $e->isRetryable()) {
                    throw $e;
                }

                // Short jittered pause, but never past the deadline.
                usleep((int) min(random_int(300, 900) * 1000, max(0, ($deadline - microtime(true) - 1) * 1e6)));
            }
        }
    }

    /** @return array<string, mixed> */
    private function send(string $key, array $body, float $timeout): array
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->connectTimeout(min(5, $timeout))
                ->timeout($timeout)
                ->post(config('services.gemini.base_url').'/interactions', $body);
        } catch (ConnectionException $e) {
            throw new GeminiException('timeout', 'Gemini did not respond in time.');
        }

        if ($response->successful()) {
            $json = $response->json();

            if (! is_array($json) || ! isset($json['status'])) {
                throw new GeminiException('bad_response', 'Gemini returned an unexpected body.', $response->status());
            }

            return $json;
        }

        throw GeminiException::fromResponse($response);
    }
}
