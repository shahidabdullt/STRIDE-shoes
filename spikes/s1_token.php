<?php

/**
 * Spike S1: which auth_tokens request body does the REST API accept?
 *
 *   php spikes/s1_token.php            # try every variant, hands-free setup
 *   php spikes/s1_token.php b hold_to_talk
 *
 * Prints HTTP status, latency and the redacted response for each variant and
 * saves them under spikes/fixtures/. Token values are never printed.
 * The lock test itself (does the browser's setup get ignored?) runs in the
 * browser page: spikes/live/index.html.
 */

require __DIR__.'/lib/live.php';

$variants = isset($argv[1]) ? [$argv[1]] : ['a', 'b', 'bw', 'bm', 'none'];
$mode = $argv[2] ?? 'hands_free';

foreach ($variants as $variant) {
    $res = spike_mint_token($variant, $mode);
    $name = is_array($res['body']) ? ($res['body']['name'] ?? null) : null;

    echo str_pad("[$variant]", 7)." HTTP {$res['status']} in {$res['ms']} ms";
    echo $name ? '  token: auth_tokens/<'.strlen($name).' chars>' : '';
    echo "\n";

    if ($res['status'] !== 200) {
        echo '  '.str_replace("\n", "\n  ", spike_redact($res['body']))."\n";
    } else {
        // Anything other than "name" echoed back tells us how the server parsed the body.
        echo '  response keys: '.implode(', ', array_keys($res['body']))."\n";
        if (str_contains($res['raw'], spike_api_key())) {
            echo "  !! API KEY FOUND IN RESPONSE\n";
        }
    }

    spike_save_fixture("s1_token_{$variant}_{$mode}", [
        'request' => ['POST' => GEMINI_BASE.'/auth_tokens', 'body' => $res['request']],
        'response' => ['status' => $res['status'], 'body' => $res['body']],
    ]);
}
