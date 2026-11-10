<?php

/**
 * Spike S4: Interactions API, stateless (store=false), function calling, structured output.
 *
 *   php spikes/s4_text.php [model]
 *
 * Saves redacted request/response pairs to spikes/fixtures/s4_*.json for later
 * use as Http::fake() fixtures.
 */

require __DIR__.'/lib/gemini.php';
require __DIR__.'/lib/stub_tools.php';

$model = $argv[1] ?? spike_env('GEMINI_TEXT_MODEL') ?? 'gemini-3.8-flash';
echo "Model: $model\n";

$tools = [[
    'type' => 'function',
    'name' => 'check_size_availability',
    'description' => 'Check whether a Stride shoe is in stock in a given US size.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'Product slug, e.g. "trailblazer"'],
            'size' => ['type' => 'number', 'description' => 'US size, 6 to 13 in half sizes'],
        ],
        'required' => ['slug', 'size'],
    ],
]];

$system = 'You are the assistant for Stride, a fictional shoe store. '
    .'Answer size questions only from check_size_availability results. Be brief.';

// ---- Part 1: stateless tool loop (max 4 rounds) ----

$input = [[
    'type' => 'user_input',
    'content' => [['type' => 'text', 'text' => 'Is the Trailblazer available in size 9? If not, check 9.5 too.']],
]];

for ($round = 1; $round <= 4; $round++) {
    $request = ['model' => $model, 'store' => false, 'system_instruction' => $system, 'tools' => $tools, 'input' => $input];
    $res = spike_post('/interactions', $request);
    $body = $res['body'];

    echo "\n[round $round] HTTP {$res['status']} in {$res['ms']} ms";
    spike_save_fixture("s4_tool_round{$round}", ['request' => $request, 'response' => ['status' => $res['status'], 'body' => $body]]);

    if ($res['status'] !== 200 || ! is_array($body)) {
        echo "\n".spike_redact($body)."\n";
        exit(1);
    }

    $steps = $body['steps'] ?? [];
    echo "  status={$body['status']}  steps=".implode(',', array_column($steps, 'type'))."\n";
    echo '  usage: '.json_encode($body['usage'] ?? null)."\n";

    $calls = array_values(array_filter($steps, fn ($s) => ($s['type'] ?? '') === 'function_call'));
    if (! $calls) {
        foreach ($steps as $s) {
            if (($s['type'] ?? '') === 'model_output') {
                echo '  answer: '.implode('', array_column($s['content'] ?? [], 'text'))."\n";
            }
        }
        break;
    }

    // Stateless history: previous input + all returned steps (thoughts included) + our results.
    $input = array_merge($input, $steps);
    foreach ($calls as $call) {
        $result = spike_check_size($call['arguments'] ?? []);
        echo "  call {$call['name']}(".json_encode($call['arguments'] ?? []).') id='.($call['id'] ?? '?').' -> '.json_encode($result)."\n";
        $input[] = [
            'type' => 'function_result',
            'name' => $call['name'],
            'call_id' => $call['id'],
            'result' => [['type' => 'text', 'text' => json_encode($result)]],
        ];
    }
}

// ---- Part 2: structured output (answer-outcome classifier shape from BLUEPRINT §2.6) ----

$transcript = "customer: Do you have the Trailblazer in size 10?\n"
    ."assistant: [tool check_size_availability -> in_stock=true, band=available] Yes, size 10 is in stock.\n"
    ."customer: Do you ship to Iceland?\n"
    ."assistant: I'm sorry, I don't have information about shipping to Iceland.\n"
    ."customer: Tell me a joke.\n"
    .'assistant: I can only help with Stride shoes and orders.';

$schema = [
    'type' => 'object',
    'properties' => [
        'questions' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'question' => ['type' => 'string'],
                    'topic' => ['type' => 'string'],
                    'outcome' => ['type' => 'string', 'enum' => ['answered', 'not_answered', 'out_of_scope']],
                    'source' => ['type' => 'string', 'enum' => ['catalog', 'knowledge', 'both', 'none']],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['question', 'topic', 'outcome', 'source', 'reason'],
            ],
        ],
    ],
    'required' => ['questions'],
];

$request = [
    'model' => $model,
    'store' => false,
    'system_instruction' => 'Classify each distinct customer question. "answered" only if supported by a tool result shown in the transcript.',
    'input' => $transcript,
    'response_format' => ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema],
];
$res = spike_post('/interactions', $request);
echo "\n[structured] HTTP {$res['status']} in {$res['ms']} ms\n";
spike_save_fixture('s4_structured', ['request' => $request, 'response' => ['status' => $res['status'], 'body' => $res['body']]]);

if ($res['status'] === 200) {
    foreach ($res['body']['steps'] ?? [] as $s) {
        if (($s['type'] ?? '') === 'model_output') {
            $text = implode('', array_column($s['content'] ?? [], 'text'));
            $parsed = json_decode($text, true);
            echo $parsed === null ? "  NOT valid JSON: $text\n" : '  parsed: '.json_encode($parsed, JSON_PRETTY_PRINT)."\n";
        }
    }
} else {
    echo spike_redact($res['body'])."\n";
}

// ---- Part 3: error shapes ----

$res = spike_post('/interactions', ['model' => $model, 'store' => false, 'input' => 'hi'], 'invalid-key-for-spike');
echo "\n[bad key] HTTP {$res['status']}\n".spike_redact($res['body'])."\n";
spike_save_fixture('s4_error_bad_key', ['status' => $res['status'], 'body' => $res['body']]);

$res = spike_post('/interactions', ['model' => 'no-such-model', 'store' => false, 'input' => 'hi']);
echo "\n[bad model] HTTP {$res['status']}\n".spike_redact($res['body'])."\n";
spike_save_fixture('s4_error_bad_model', ['status' => $res['status'], 'body' => $res['body']]);
