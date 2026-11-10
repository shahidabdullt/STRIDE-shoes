<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A failed Gemini call. `kind` is a short stable code that is stored on the message (error_code):
 * timeout, rate_limited, unavailable, auth, bad_request, not_found, bad_response, not_configured.
 */
class GeminiException extends RuntimeException
{
    public function __construct(
        public readonly string $kind,
        string $message,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }

    public function isRetryable(): bool
    {
        return in_array($this->kind, ['timeout', 'rate_limited', 'unavailable'], true);
    }

    /**
     * Error bodies differ by error (Phase 0): a bad key gives a JSON array wrapping a google.rpc error,
     * other errors give {"error": {"message", "code": "<string>"}}. Neither ever contains the key.
     */
    public static function fromResponse(Response $response): self
    {
        $json = $response->json();
        $error = is_array($json) && array_is_list($json) ? ($json[0]['error'] ?? []) : ($json['error'] ?? []);
        $message = is_array($error) ? (string) ($error['message'] ?? 'Gemini error') : 'Gemini error';
        $status = $response->status();

        $kind = match (true) {
            $status === 429 => 'rate_limited',
            $status >= 500 => 'unavailable',
            $status === 401 || $status === 403 => 'auth',
            $status === 404 => 'not_found',
            str_contains(json_encode($error) ?: '', 'API_KEY_INVALID') => 'auth',
            default => 'bad_request',
        };

        return new self($kind, $message, $status);
    }
}
