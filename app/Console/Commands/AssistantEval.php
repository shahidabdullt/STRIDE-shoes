<?php

namespace App\Console\Commands;

use App\Assistant\ChatResponder;
use App\Models\AiConfigVersion;
use App\Models\Cart;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('assistant:eval {--only= : Comma-separated case names} {--pause=12 : Seconds between API turns (free tier)} {--keep : Keep the eval conversations and carts}')]
#[Description('Run the golden prompts against the real Gemini API and write a Markdown report (not for CI)')]
class AssistantEval extends Command
{
    /** Price-like amounts in a text, in cents: "$129.00", "$6", "$1,200". */
    private const MONEY = '/\$\s?(\d{1,3}(?:,\d{3})*|\d+)(?:\.(\d{2}))?/';

    public function handle(ChatResponder $responder): int
    {
        if (blank(config('services.gemini.key'))) {
            $this->error('GEMINI_API_KEY is not set.');

            return self::FAILURE;
        }

        $config = AiConfigVersion::active();
        if ($config === null) {
            $this->error('No active AI config version. Run the seeders first.');

            return self::FAILURE;
        }

        $cases = require resource_path('assistant/golden-set.php');
        if ($only = $this->option('only')) {
            $cases = array_intersect_key($cases, array_flip(array_map('trim', explode(',', $only))));
        }

        $this->info('AI config v'.$config->version.' · '.$config->text_model.' · '.count($cases).' cases');
        $knownAmounts = $this->knownAmounts();
        $rows = [];
        $conversations = [];
        $first = true;

        foreach ($cases as $name => $case) {
            $ordersBefore = Order::count();
            $conversation = Conversation::create([
                'uuid' => (string) Str::uuid(),
                'channel' => 'text',
                'device_class' => 'desktop',
                'visitor_id' => (string) Str::uuid(),
                'ai_config_version_id' => $config->id,
                'status' => 'active',
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
            $conversations[] = $conversation;

            $replies = [];
            foreach ($case['messages'] as $message) {
                if (! $first) {
                    sleep((int) $this->option('pause'));
                }
                $first = false;
                $replies[] = $responder->respond($conversation, $message);
            }

            // A quota error says nothing about the assistant's quality: stop instead of failing every remaining case.
            if ($conversation->messages()->where('role', 'assistant')->where('error_code', 'rate_limited')->exists()) {
                $this->warn("Stopped at {$name}: Gemini quota exhausted (HTTP 429). See storage/logs/laravel.log for Google's message; on the free tier the daily request limit is low. Retry later or use a paid key.");
                $rows = [];
                break;
            }

            $rows[] = $this->score($name, $case, $conversation->fresh(), $replies, $ordersBefore, $knownAmounts);
            $last = end($rows);
            $this->line(sprintf('%s %-20s %s', $last['pass'] ? '<info>PASS</info>' : '<error>FAIL</error>', $name, implode('; ', $last['problems'])));
        }

        if ($rows === []) {
            $this->cleanUp($conversations);

            return self::FAILURE;
        }

        $passed = count(array_filter($rows, fn ($r) => $r['pass']));
        $rate = count($rows) ? round(100 * $passed / count($rows), 1) : 0;
        $path = 'eval/assistant-eval-'.now()->format('Ymd-His').'.md';
        Storage::disk('local')->put($path, $this->report($config, $rows, $rate));

        $this->cleanUp($conversations);

        $this->newLine();
        $this->info("{$passed}/".count($rows)." passed ({$rate}%). Report: storage/app/private/{$path}");

        return $rate >= 95 ? self::SUCCESS : self::FAILURE;
    }

    /** Eval conversations and their carts are synthetic, so they are removed unless --keep is given. */
    private function cleanUp(array $conversations): void
    {
        if ($this->option('keep')) {
            return;
        }

        foreach ($conversations as $conversation) {
            Cart::where('visitor_id', $conversation->visitor_id)->delete();
            $conversation->delete();
        }
    }

    /** @return array{name: string, pass: bool, problems: list<string>, tools: list<string>, reply: string} */
    private function score(string $name, array $case, Conversation $conversation, array $replies, int $ordersBefore, array $knownAmounts): array
    {
        $problems = [];
        $tools = $conversation->messages()->where('role', 'tool')->pluck('tool_name')->all();
        $reply = (string) end($replies)['reply'];

        foreach ($replies as $r) {
            if ($r['error']) {
                $problems[] = 'assistant error ('.$conversation->messages()->where('role', 'assistant')->where('status', 'error')->value('error_code').')';
                break;
            }
        }

        if (isset($case['tools']) && array_intersect($case['tools'], $tools) === []) {
            $problems[] = 'expected one of ['.implode(', ', $case['tools']).']';
        }

        $reported = in_array('report_knowledge_gap', $tools, true);
        if (isset($case['gap']) && $case['gap'] !== $reported) {
            $problems[] = $case['gap'] ? 'gap not reported' : 'gap reported unexpectedly';
        }

        if (isset($case['reply']) && ! preg_match('/'.$case['reply'].'/iu', $reply)) {
            $problems[] = "reply does not match /{$case['reply']}/";
        }

        if (isset($case['not_reply']) && preg_match('/'.$case['not_reply'].'/iu', $reply)) {
            $problems[] = "reply matches forbidden /{$case['not_reply']}/";
        }

        if (isset($case['cart'])) {
            $count = Cart::open()->where('visitor_id', $conversation->visitor_id)->first()?->totalQuantity() ?? 0;
            if ($count !== $case['cart']) {
                $problems[] = "cart has {$count}, expected {$case['cart']}";
            }
        }

        if (Order::count() !== $ordersBefore) {
            $problems[] = 'an order was created';
        }

        // Every price the assistant states must exist: in the catalog, the store rules, a tool result or the customer's own words.
        $allowed = [
            ...$knownAmounts,
            ...$this->amounts($conversation->messages()->where('role', 'tool')->pluck('tool_result')->map(fn ($r) => json_encode($r))->join(' ')),
            ...$this->amounts(implode(' ', $case['messages'])),
        ];
        $invented = array_diff($this->amounts(collect($replies)->pluck('reply')->join(' ')), $allowed);
        if ($invented !== []) {
            $problems[] = 'ungrounded price(s): '.implode(', ', array_map(fn ($c) => '$'.number_format($c / 100, 2), $invented));
        }

        return ['name' => $name, 'pass' => $problems === [], 'problems' => $problems, 'tools' => array_values(array_unique($tools)), 'reply' => $reply];
    }

    /** @return list<int> */
    private function knownAmounts(): array
    {
        $text = KnowledgeArticle::pluck('body')->join(' ').' '.Faq::pluck('answer')->join(' ');

        return array_values(array_unique([
            0,
            (int) config('store.shipping_flat_cents'),
            (int) config('store.free_shipping_threshold_cents'),
            ...Product::pluck('price_cents')->map(fn ($c) => (int) $c)->all(),
            ...$this->amounts($text),
        ]));
    }

    /** @return list<int> */
    private function amounts(string $text): array
    {
        preg_match_all(self::MONEY, $text, $m, PREG_SET_ORDER);

        return array_values(array_unique(array_map(
            fn ($match) => (int) str_replace(',', '', $match[1]) * 100 + (int) ($match[2] ?? 0),
            $m,
        )));
    }

    private function report(AiConfigVersion $config, array $rows, float $rate): string
    {
        $lines = [
            '# Assistant eval '.now()->toDateTimeString(),
            '',
            "AI config v{$config->version}, model `{$config->text_model}`, pass rate **{$rate}%** (target ≥ 95%).",
            '',
            '| Case | Result | Tools used | Problems | Final reply |',
            '|---|---|---|---|---|',
        ];

        foreach ($rows as $r) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $r['name'],
                $r['pass'] ? 'pass' : '**fail**',
                implode(', ', $r['tools']) ?: '—',
                str_replace('|', '\|', implode('; ', $r['problems'])) ?: '—',
                str_replace(['|', "\n"], ['\|', ' '], Str::limit($r['reply'], 200)),
            );
        }

        return implode("\n", $lines)."\n";
    }
}
