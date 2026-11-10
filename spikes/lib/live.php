<?php

require_once __DIR__.'/gemini.php';

const SPIKE_LIVE_MODEL = 'gemini-3.8-live';

/**
 * Server-side ("locked") Live setup in raw BidiGenerateContentSetup JSON form.
 * The system instruction contains a marker so the lock test can tell whether
 * the locked instruction or a client-supplied one is in effect.
 */
function spike_live_setup(string $mode): array
{
    $activity = $mode === 'hold_to_talk'
        ? ['disabled' => true]
        : ['disabled' => false, 'silenceDurationMs' => 700];

    return [
        'model' => 'models/'.(spike_env('GEMINI_LIVE_MODEL') ?? SPIKE_LIVE_MODEL),
        'generationConfig' => [
            'responseModalities' => ['AUDIO'],
        ],
        'systemInstruction' => [
            'parts' => [[
                'text' => 'You are the voice assistant for Stride, a fictional shoe store. '
                    .'Begin your very first reply with the exact words "Stride here". '
                    .'Keep answers under three sentences. '
                    .'For any question about a shoe size, call check_size_availability and answer only from its result. '
                    .'If you hear background noise but no question, stay silent.',
            ]],
        ],
        'tools' => [[
            'functionDeclarations' => [[
                'name' => 'check_size_availability',
                'description' => 'Check whether a Stride shoe is in stock in a given US size.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'slug' => ['type' => 'STRING', 'description' => 'Product slug, e.g. "trailblazer"'],
                        'size' => ['type' => 'NUMBER', 'description' => 'US size, 6 to 13 in half sizes'],
                    ],
                    'required' => ['slug', 'size'],
                ],
                'behavior' => 'BLOCKING',
            ]],
        ]],
        'realtimeInputConfig' => [
            'automaticActivityDetection' => $activity,
        ],
        'inputAudioTranscription' => new stdClass,
        'outputAudioTranscription' => new stdClass,
        'sessionResumption' => new stdClass,
        'contextWindowCompression' => ['slidingWindow' => new stdClass],
    ];
}

/**
 * The SDK-style LiveConnectConfig shape from the ephemeral-token guide:
 * the generation config fields are flattened into "config".
 */
function spike_live_connect_config(array $setup): array
{
    $config = $setup;
    unset($config['model'], $config['generationConfig']);

    return ['responseModalities' => $setup['generationConfig']['responseModalities']] + $config;
}

/**
 * Token body variants under test (the conflict recorded in BLUEPRINT §3.2):
 *   a   liveConnectConstraints {model, config}          (ephemeral-token guide)
 *   b   bidiGenerateContentSetup, no fieldMask           (API reference: client setup ignored)
 *   bw  same as b, wrapped in {"authToken": {...}}        (CreateAuthTokenRequest field name)
 *   bm  b + fieldMask listing every field except sessionResumption: the masked fields
 *       are taken from the token (locked), so the client can still send a resumption handle
 *   none  no config at all                                 (control: unlocked token)
 */
function spike_token_body(string $variant, string $mode, int $ttl = 720, int $newSessionTtl = 60, int $uses = 1): array
{
    $setup = spike_live_setup($mode);
    $base = [
        'uses' => $uses,
        'expireTime' => spike_iso($ttl),
        'newSessionExpireTime' => spike_iso($newSessionTtl),
    ];

    return match ($variant) {
        'a' => $base + ['liveConnectConstraints' => [
            'model' => $setup['model'],
            'config' => spike_live_connect_config($setup),
        ]],
        'b' => $base + ['bidiGenerateContentSetup' => $setup],
        'bw' => ['authToken' => $base + ['bidiGenerateContentSetup' => $setup]],
        // The API rejects setup fields missing from a non-empty mask, so sessionResumption
        // is left out of the token entirely and supplied by the client.
        'bm' => $base + [
            'bidiGenerateContentSetup' => array_diff_key($setup, ['sessionResumption' => true]),
            'fieldMask' => implode(',', array_diff(array_keys($setup), ['sessionResumption'])),
        ],
        'none' => $base,
        default => throw new InvalidArgumentException("Unknown variant $variant"),
    };
}

/**
 * @return array{status:int, body:mixed, raw:string, ms:int, request:array}
 */
function spike_mint_token(string $variant, string $mode, int $ttl = 720, int $newSessionTtl = 60, int $uses = 1): array
{
    $request = spike_token_body($variant, $mode, $ttl, $newSessionTtl, $uses);

    return spike_post('/auth_tokens', $request) + ['request' => $request];
}
