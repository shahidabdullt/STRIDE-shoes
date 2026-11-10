<?php

namespace App\Assistant;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Services\Gemini\GeminiException;
use App\Services\Gemini\GeminiTextClient;
use App\Support\Money;
use App\Support\Redactor;
use Illuminate\Support\Facades\Log;

/**
 * One text chat turn (BLUEPRINT §2.4): store the customer's message, run the bounded tool loop against
 * Gemini in stateless mode, store every tool call and the reply, and return what the widget shows.
 * A Gemini failure never surfaces as an HTTP error: the customer gets a friendly fallback, and the
 * assistant message is stored with status=error so the admin can see it.
 */
class ChatResponder
{
    public function __construct(
        private GeminiTextClient $client,
        private ToolRegistry $registry,
        private PromptBuilder $prompts,
    ) {}

    /**
     * @return array{reply: string, error: ?string, products: list<array<string, mixed>>}
     */
    public function respond(Conversation $conversation, string $text): array
    {
        $startedAt = microtime(true);
        $deadline = $startedAt + (int) config('assistant.turn_deadline_seconds');
        $config = $conversation->aiConfigVersion;
        $context = new ToolContext($conversation);

        $this->store($conversation, ['role' => 'user', 'content' => Redactor::redact($text)]);

        $input = $this->history($conversation);
        $usage = ['input' => 0, 'output' => 0];
        $productSlugs = [];
        $reply = null;
        $errorCode = null;

        try {
            for ($round = 1; $round <= (int) config('assistant.max_tool_rounds'); $round++) {
                $interaction = $this->client->createInteraction([
                    'model' => $config->text_model,
                    'system_instruction' => $this->prompts->build($config, 'text'),
                    'tools' => $this->registry->interactionsDeclarations(),
                    'generation_config' => [
                        'temperature' => (float) $config->temperature,
                        'max_output_tokens' => (int) $config->max_output_tokens,
                    ],
                    'input' => $input,
                ], $deadline);

                $usage['input'] += (int) ($interaction['usage']['total_input_tokens'] ?? 0);
                // Thinking tokens are billed as output and count against max_output_tokens.
                $usage['output'] += (int) ($interaction['usage']['total_output_tokens'] ?? 0)
                    + (int) ($interaction['usage']['total_thought_tokens'] ?? 0);

                $steps = is_array($interaction['steps'] ?? null) ? $interaction['steps'] : [];
                $calls = array_values(array_filter($steps, fn ($s) => is_array($s) && ($s['type'] ?? null) === 'function_call'));

                if ($calls === []) {
                    $reply = $this->outputText($steps);
                    $errorCode = match (true) {
                        $reply === '' => 'empty_reply',
                        ($interaction['status'] ?? null) === 'incomplete' => 'incomplete',
                        default => null,
                    };
                    break;
                }

                // Stateless mode: echo the model's steps (thought signatures included) before our results.
                array_push($input, ...$steps);

                foreach ($calls as $call) {
                    $name = (string) ($call['name'] ?? '');
                    $outcome = $this->registry->execute($name, $call['arguments'] ?? [], $context);
                    $productSlugs = [...$productSlugs, ...$this->mentionedProducts($name, $outcome['result'])];

                    $this->store($conversation, [
                        'role' => 'tool',
                        'content' => $name,
                        'tool_name' => mb_substr($name, 0, 64),
                        'tool_args' => $outcome['args'],
                        'tool_result' => ToolRegistry::forStorage($outcome['result']),
                        'status' => $outcome['status'],
                        'error_code' => $outcome['error_code'],
                    ]);

                    $input[] = [
                        'type' => 'function_result',
                        'name' => $name,
                        'call_id' => (string) ($call['id'] ?? ''),
                        'result' => [['type' => 'text', 'text' => json_encode($outcome['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                    ];
                }
            }

            $errorCode ??= $reply === null ? 'tool_round_limit' : null;
        } catch (GeminiException $e) {
            Log::warning('Gemini text call failed', ['kind' => $e->kind, 'status' => $e->status, 'message' => mb_substr($e->getMessage(), 0, 300), 'conversation' => $conversation->id]);
            $errorCode = $e->kind;
        }

        // An incomplete reply with some text is still shown; everything else without text is a failure.
        $failed = $reply === null || $reply === '';
        $reply = $failed ? $this->fallback() : $reply;

        $this->store($conversation, [
            'role' => 'assistant',
            'content' => $reply,
            'status' => $failed ? 'error' : 'ok',
            'error_code' => $errorCode,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'input_tokens' => $usage['input'],
            'output_tokens' => $usage['output'],
        ]);

        $conversation->forceFill([
            'last_activity_at' => now(),
            'input_tokens' => $conversation->input_tokens + $usage['input'],
            'output_tokens' => $conversation->output_tokens + $usage['output'],
        ])->save();

        return [
            'reply' => $reply,
            'error' => $failed ? 'assistant_unavailable' : null,
            'products' => $failed ? [] : $this->productCards(array_unique($productSlugs)),
        ];
    }

    /**
     * The last N user/assistant messages as Interactions input steps. Failed replies are left out,
     * and the window always starts with a customer message.
     *
     * @return list<array<string, mixed>>
     */
    private function history(Conversation $conversation): array
    {
        $messages = $conversation->messages()->reorder()
            ->whereIn('role', ['user', 'assistant'])->where('status', 'ok')
            ->orderByDesc('id')->limit((int) config('assistant.history_messages'))->get()
            ->reverse()->values()
            ->skipUntil(fn (Message $m) => $m->role === 'user');

        return $messages->map(fn (Message $m) => [
            'type' => $m->role === 'user' ? 'user_input' : 'model_output',
            'content' => [['type' => 'text', 'text' => $m->content]],
        ])->values()->all();
    }

    private function outputText(array $steps): string
    {
        $text = '';

        foreach ($steps as $step) {
            if (is_array($step) && ($step['type'] ?? null) === 'model_output') {
                foreach ((array) ($step['content'] ?? []) as $part) {
                    $text .= is_array($part) && ($part['type'] ?? 'text') === 'text' ? (string) ($part['text'] ?? '') : '';
                }
            }
        }

        return trim($text);
    }

    /** @return list<string> */
    private function mentionedProducts(string $tool, array $result): array
    {
        return match ($tool) {
            'search_products' => array_column($result['products'] ?? [], 'slug'),
            'get_product', 'check_size_availability' => isset($result['slug']) ? [$result['slug']] : [],
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function productCards(array $slugs): array
    {
        $slugs = array_slice(array_values($slugs), 0, 4);

        if ($slugs === []) {
            return [];
        }

        $products = Product::active()->with('images')->whereIn('slug', $slugs)->get()->keyBy('slug');

        return collect($slugs)->filter(fn ($s) => $products->has($s))->map(function (string $slug) use ($products) {
            $product = $products[$slug];
            $image = $product->images->first();

            return [
                'slug' => $product->slug,
                'name' => $product->name,
                'price' => Money::format($product->price_cents),
                'image' => $image ? asset($image->path) : null,
                'url' => route('products.show', $product->slug),
            ];
        })->values()->all();
    }

    private function fallback(): string
    {
        return "Sorry, I can't answer right now. Please try again in a moment, or find answers on our help page: ".route('faqs.index');
    }

    private function store(Conversation $conversation, array $attributes): Message
    {
        $message = $conversation->messages()->create($attributes);

        if ($attributes['role'] !== 'tool') {
            $conversation->increment('message_count');
        }

        return $message;
    }
}
