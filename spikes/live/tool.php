<?php

/**
 * Spike stand-in for the Laravel tool endpoint (S3). Localhost only.
 * POST tool.php?delay_ms=0  {"name": "...", "args": {...}}
 */

require __DIR__.'/../lib/stub_tools.php';

header('Content-Type: application/json');

if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit(json_encode(['error' => 'localhost only']));
}

$call = json_decode(file_get_contents('php://input'), true) ?? [];
usleep(max(0, min(10000, (int) ($_GET['delay_ms'] ?? 0))) * 1000);

echo json_encode(($call['name'] ?? '') === 'check_size_availability'
    ? spike_check_size((array) ($call['args'] ?? []))
    : ['error' => 'unknown_tool']);
