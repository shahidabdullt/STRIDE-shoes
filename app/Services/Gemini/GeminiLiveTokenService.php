<?php

namespace App\Services\Gemini;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Mints single-use ephemeral tokens for browser voice sessions (POST v1beta/auth_tokens).
 * Body shape verified in Phase 0 (S1): AuthToken fields at the top level, the locked setup in
 * `bidiGenerateContentSetup`, and a fieldMask that must list every field in that setup.
 * Only the token's `name` ever leaves the server; the API key never does.
 */
class GeminiLiveTokenService
{
    /** Hard ceiling from AC-V2. */
    private const MAX_TTL_SECONDS = 900;

    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     *
     * @throws GeminiException
     */
    public function mint(array $setup, string $fieldMask, int $sessionSeconds): array
    {
        $key = (string) config('services.gemini.key');

        if ($key === '') {
            throw new GeminiException('not_configured', 'GEMINI_API_KEY is not set.');
        }

        $now = CarbonImmutable::now('UTC');
        // Long enough to resume up to the session cap, never longer than 15 minutes.
        $ttl = min(self::MAX_TTL_SECONDS, max((int) config('services.gemini.live_token_ttl'), $sessionSeconds + 60));
        $expiresAt = $now->addSeconds($ttl);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout((int) config('services.gemini.timeout'))
                ->post(config('services.gemini.base_url').'/auth_tokens', [
                    'uses' => 1,
                    'expireTime' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
                    'newSessionExpireTime' => $now->addSeconds(min(60, (int) config('services.gemini.live_new_session_ttl')))->format('Y-m-d\TH:i:s\Z'),
                    'bidiGenerateContentSetup' => $setup,
                    'fieldMask' => $fieldMask,
                ]);
        } catch (ConnectionException) {
            throw new GeminiException('timeout', 'Gemini did not respond in time.');
        }

        if (! $response->successful()) {
            throw GeminiException::fromResponse($response);
        }

        $token = $response->json('name');

        if (! is_string($token) || ! str_starts_with($token, 'auth_tokens/')) {
            throw new GeminiException('bad_response', 'The token response had no token name.', $response->status());
        }

        return ['token' => $token, 'expires_at' => $expiresAt];
    }
}
