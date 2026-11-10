<?php

/**
 * Phase 0 spike helpers. Throwaway code, not part of the app.
 *
 * Reads GEMINI_API_KEY from the project .env without printing it, sends JSON
 * requests with the curl extension, and redacts secrets before anything is
 * printed or saved as a fixture.
 */
const GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta';

function spike_env(string $name): ?string
{
    static $env = null;

    if ($env === null) {
        $env = [];
        $path = dirname(__DIR__, 2).'/.env';

        foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $m)) {
                $env[$m[1]] = trim($m[2], "\"'");
            }
        }
    }

    $value = getenv($name) ?: ($env[$name] ?? null);

    return $value === '' ? null : $value;
}

function spike_api_key(): string
{
    $key = spike_env('GEMINI_API_KEY');

    if ($key === null) {
        fwrite(STDERR, "GEMINI_API_KEY is not set in .env\n");
        exit(2);
    }

    return $key;
}

/**
 * @return array{status:int, body:mixed, raw:string, ms:int}
 */
function spike_post(string $path, array $body, ?string $apiKey = null): array
{
    $ch = curl_init(GEMINI_BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: '.($apiKey ?? spike_api_key()),
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES),
    ]);

    $start = hrtime(true);
    $raw = curl_exec($ch);
    $ms = (int) ((hrtime(true) - $start) / 1e6);

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return ['status' => 0, 'body' => ['curl_error' => $error], 'raw' => '', 'ms' => $ms];
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => json_decode($raw, true) ?? $raw, 'raw' => $raw, 'ms' => $ms];
}

/**
 * Masks the API key, ephemeral token values and thought signatures.
 */
function spike_redact(mixed $data): mixed
{
    $json = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $key = spike_env('GEMINI_API_KEY');
    if ($key !== null) {
        $json = str_replace($key, '<REDACTED_API_KEY>', $json);
    }

    $json = preg_replace('#auth_tokens/[A-Za-z0-9._\-]+#', 'auth_tokens/<REDACTED_TOKEN>', $json);
    $json = preg_replace('/AIza[0-9A-Za-z_\-]{20,}/', '<REDACTED_API_KEY>', $json);
    $json = preg_replace('/("signature"\s*:\s*")[^"]+"/', '$1<REDACTED_SIGNATURE>"', $json);

    return $json;
}

function spike_save_fixture(string $name, mixed $data): void
{
    $dir = dirname(__DIR__).'/fixtures';
    is_dir($dir) || mkdir($dir, 0777, true);
    file_put_contents("$dir/$name.json", spike_redact($data).PHP_EOL);
    echo "  saved fixtures/$name.json\n";
}

function spike_iso(int $secondsFromNow): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() + $secondsFromNow);
}
