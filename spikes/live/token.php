<?php

/**
 * Spike token endpoint for the browser page (localhost only).
 * GET token.php?variant=b&mode=hands_free&nst=60&uses=1
 */

require __DIR__.'/../lib/live.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit(json_encode(['error' => 'localhost only']));
}

$variant = in_array($_GET['variant'] ?? 'b', ['a', 'b', 'bw', 'bm', 'none'], true) ? ($_GET['variant'] ?? 'b') : 'b';
$mode = ($_GET['mode'] ?? '') === 'hold_to_talk' ? 'hold_to_talk' : 'hands_free';
$newSessionTtl = max(5, min(60, (int) ($_GET['nst'] ?? 60)));
$uses = max(0, min(3, (int) ($_GET['uses'] ?? 1)));

$res = spike_mint_token($variant, $mode, 720, $newSessionTtl, $uses);

if ($res['status'] !== 200 || ! isset($res['body']['name'])) {
    http_response_code(502);
    exit(json_encode(['error' => 'mint_failed', 'status' => $res['status'], 'detail' => json_decode(spike_redact($res['body']))]));
}

echo json_encode([
    'token' => $res['body']['name'],
    'model' => 'models/'.(spike_env('GEMINI_LIVE_MODEL') ?? SPIKE_LIVE_MODEL),
    'variant' => $variant,
    'mode' => $mode,
    'mint_ms' => $res['ms'],
    'ws_url' => 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained',
]);
