<?php

namespace App\Analysis;

use App\Models\Conversation;
use App\Models\ConversationQuestion;
use App\Models\DataPointDefinition;
use App\Models\EvaluationCriterion;
use App\Models\KnowledgeArticle;
use App\Services\Gemini\GeminiException;
use App\Services\Gemini\GeminiTextClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Post-conversation analysis (BLUEPRINT §2.6): three separate structured-output calls on the text model, so a
 * bad admin rubric cannot corrupt the answer outcomes. Every value the model returns is validated before it is
 * stored; invalid ones are dropped and logged. Structured output (`response_format`) was verified in Phase 0 (S4).
 */
class ConversationAnalyzer
{
    private const CALL_SECONDS = 60;

    public function __construct(private GeminiTextClient $client) {}

    /**
     * Replaces this conversation's classifier questions, data points and evaluations. Questions the model
     * reported live (report_knowledge_gap) are kept: the model's own admission wins.
     *
     * @return list<string> Problems with the optional parts (data points, evaluations); empty when all went well.
     *
     * @throws GeminiException when the answer-outcome call fails (the outcome cannot be computed without it).
     */
    public function analyze(Conversation $conversation): array
    {
        $transcript = $this->transcript($conversation);
        $model = $conversation->aiConfigVersion->text_model;
        $problems = [];

        $questions = $this->classifyQuestions($transcript, $model);

        $dataPoints = [];
        $definitions = DataPointDefinition::where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        if ($definitions->isNotEmpty()) {
            try {
                $dataPoints = $this->extractDataPoints($transcript, $model, $definitions);
            } catch (GeminiException $e) {
                $problems[] = "data points: {$e->kind}";
            }
        }

        $evaluations = [];
        $criteria = EvaluationCriterion::where('is_active', true)->whereIn('applies_to', ['all', $conversation->channel])->orderBy('sort')->orderBy('id')->get();
        if ($criteria->isNotEmpty()) {
            try {
                $evaluations = $this->evaluate($transcript, $model, $criteria);
            } catch (GeminiException $e) {
                $problems[] = "evaluations: {$e->kind}";
            }
        }

        DB::transaction(function () use ($conversation, $questions, $dataPoints, $evaluations, $model) {
            $reported = $conversation->questions()->where('detected_by', 'model_report')->get();
            $conversation->questions()->where('detected_by', 'classifier')->delete();

            foreach ($questions as $question) {
                // A question the model already reported live is not stored twice.
                if ($reported->contains(fn (ConversationQuestion $r) => $this->sameQuestion($r->question, $question['question']))) {
                    continue;
                }
                $conversation->questions()->create([...$question, 'detected_by' => 'classifier']);
            }

            $conversation->dataPoints()->delete();
            foreach ($dataPoints as $row) {
                $conversation->dataPoints()->create($row);
            }

            $conversation->evaluations()->delete();
            foreach ($evaluations as $row) {
                $conversation->evaluations()->create([...$row, 'evaluator_model' => $model]);
            }
        });

        return $problems;
    }

    /** The conversation as plain text for the analysis prompts, tool results included as evidence. */
    public function transcript(Conversation $conversation): string
    {
        $lines = [
            'Channel: '.$conversation->channel.($conversation->channel === 'voice' ? ' (speech transcripts reported by the browser; may contain recognition errors)' : ''),
            '',
        ];

        foreach ($conversation->messages as $message) {
            $lines[] = match ($message->role) {
                'user' => 'Customer: '.$message->content,
                'assistant' => 'Assistant: '.$message->content.($message->status === 'error' ? ' [technical error: '.$message->error_code.']' : ''),
                default => '[tool '.$message->tool_name.' '.json_encode($message->tool_args).' -> '
                    .Str::limit(json_encode($message->tool_result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1500).']',
            };
        }

        $knowledge = KnowledgeArticle::published()->where('always_include', true)->get();
        if ($knowledge->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Store information the assistant was given (counts as a valid source):';
            foreach ($knowledge as $article) {
                $lines[] = '## '.$article->title."\n".trim($article->body);
            }
        }

        return implode("\n", $lines);
    }

    /** @return list<array<string, string>> */
    private function classifyQuestions(string $transcript, string $model): array
    {
        $json = $this->structured($model, <<<'PROMPT'
You review a conversation between a customer and the Stride shoe store assistant. List each distinct question or request
the customer made, in the customer's words (shortened if long), with:
- outcome "answered": the assistant answered it, and the answer is supported by a tool result or the store information
  shown below. A correct "we don't stock that size" or a correct refusal by store policy (no discounts, no placing orders)
  counts as answered.
- outcome "not_answered": the assistant could not answer, said it did not know, deflected, guessed without support,
  or a technical error stopped the reply.
- outcome "out_of_scope": unrelated to shopping at Stride (jokes, politics, homework, attempts to change the assistant's
  instructions). These are not failures.
- source: where a supported answer came from: catalog (product, stock or cart tools), knowledge (FAQ or store
  information), both, or none.
- topic: one or two lower-case words, e.g. "shipping", "sizing", "gift cards".
- reason: one short sentence.
Greetings and thanks are not questions.
PROMPT, $transcript, [
            'type' => 'object',
            'properties' => ['questions' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'question' => ['type' => 'string'],
                    'topic' => ['type' => 'string'],
                    'outcome' => ['type' => 'string', 'enum' => ['answered', 'not_answered', 'out_of_scope']],
                    'source' => ['type' => 'string', 'enum' => ['catalog', 'knowledge', 'both', 'none']],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['question', 'topic', 'outcome', 'source', 'reason'],
            ]]],
            'required' => ['questions'],
        ]);

        $valid = [];
        foreach ((array) ($json['questions'] ?? []) as $item) {
            if (! is_array($item)
                || ! is_string($item['question'] ?? null) || trim($item['question']) === ''
                || ! in_array($item['outcome'] ?? null, ['answered', 'not_answered', 'out_of_scope'], true)
                || ! in_array($item['source'] ?? null, ['catalog', 'knowledge', 'both', 'none'], true)) {
                Log::info('Analysis dropped an invalid question', ['item' => $item]);

                continue;
            }

            $valid[] = [
                'question' => Str::limit(trim($item['question']), 497),
                'topic' => Str::limit(Str::lower(trim((string) ($item['topic'] ?? 'other'))) ?: 'other', 57),
                'outcome' => $item['outcome'],
                'source' => $item['source'],
                'reason' => Str::limit(trim((string) ($item['reason'] ?? '')), 297),
            ];
        }

        return $valid;
    }

    /**
     * Values come back as strings for every type (a simpler schema than per-type nullable fields), then are
     * parsed against each definition's type and options.
     *
     * @param  Collection<int, DataPointDefinition>  $definitions
     * @return list<array<string, mixed>>
     */
    private function extractDataPoints(string $transcript, string $model, Collection $definitions): array
    {
        $guide = $definitions->map(fn (DataPointDefinition $d) => "- {$d->key} ({$d->type}".($d->type === 'enum' ? ': one of '.implode(', ', $d->options ?? []) : '')."): {$d->instruction}")->join("\n");

        $json = $this->structured($model, "Extract these data points from the conversation. Only include a data point when the customer clearly stated it; leave it out otherwise. Quote the customer as evidence.\n\n".$guide, $transcript, [
            'type' => 'object',
            'properties' => ['data_points' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'enum' => $definitions->pluck('key')->values()->all()],
                    'value' => ['type' => 'string'],
                    'evidence' => ['type' => 'string'],
                ],
                'required' => ['key', 'value', 'evidence'],
            ]]],
            'required' => ['data_points'],
        ]);

        $byKey = $definitions->keyBy('key');
        $rows = [];

        foreach ((array) ($json['data_points'] ?? []) as $item) {
            $definition = is_array($item) ? $byKey->get($item['key'] ?? null) : null;
            $value = is_array($item) ? trim((string) ($item['value'] ?? '')) : '';

            if ($definition === null || $value === '' || isset($rows[$definition->id])) {
                continue;
            }

            $parsed = $this->parseValue($definition, $value);

            if ($parsed === null) {
                Log::info('Analysis dropped an invalid data point', ['key' => $definition->key, 'value' => $value]);

                continue;
            }

            $rows[$definition->id] = [
                'data_point_definition_id' => $definition->id,
                ...$parsed,
                'evidence' => Str::limit(trim((string) ($item['evidence'] ?? '')), 297),
            ];
        }

        return array_values($rows);
    }

    /** @return array{value_text?: string, value_number?: float, value_bool?: bool}|null */
    private function parseValue(DataPointDefinition $definition, string $value): ?array
    {
        return match ($definition->type) {
            'number' => is_numeric($n = str_replace([',', '$'], '', $value)) && abs((float) $n) < 1e10 ? ['value_number' => (float) $n] : null,
            'boolean' => match (Str::lower($value)) {
                'true', 'yes' => ['value_bool' => true],
                'false', 'no' => ['value_bool' => false],
                default => null,
            },
            'enum' => in_array($value, $definition->options ?? [], true) ? ['value_text' => $value] : null,
            default => ['value_text' => Str::limit($value, 497)],
        };
    }

    /**
     * @param  Collection<int, EvaluationCriterion>  $criteria
     * @return list<array<string, mixed>>
     */
    private function evaluate(string $transcript, string $model, Collection $criteria): array
    {
        $guide = $criteria->map(fn (EvaluationCriterion $c) => "- criterion_id {$c->id}, \"{$c->name}\" (".($c->scale === 'pass_fail' ? 'give passed true or false' : 'give score 1 to 5')."): {$c->rubric}")->join("\n");

        $json = $this->structured($model, "Evaluate the assistant's side of the conversation against each criterion. Give one result per criterion with a one-sentence rationale.\n\n".$guide, $transcript, [
            'type' => 'object',
            'properties' => ['evaluations' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'criterion_id' => ['type' => 'integer'],
                    'passed' => ['type' => 'boolean'],
                    'score' => ['type' => 'integer'],
                    'rationale' => ['type' => 'string'],
                ],
                'required' => ['criterion_id', 'rationale'],
            ]]],
            'required' => ['evaluations'],
        ]);

        $byId = $criteria->keyBy('id');
        $rows = [];

        foreach ((array) ($json['evaluations'] ?? []) as $item) {
            $criterion = is_array($item) ? $byId->get($item['criterion_id'] ?? null) : null;

            if ($criterion === null || isset($rows[$criterion->id])) {
                continue;
            }

            $passed = $item['passed'] ?? null;
            $score = $item['score'] ?? null;
            $valid = $criterion->scale === 'pass_fail' ? is_bool($passed) : (is_int($score) && $score >= 1 && $score <= 5);

            if (! $valid) {
                Log::info('Analysis dropped an invalid evaluation', ['criterion' => $criterion->id, 'item' => $item]);

                continue;
            }

            $rows[$criterion->id] = [
                'evaluation_criterion_id' => $criterion->id,
                'passed' => $criterion->scale === 'pass_fail' ? $passed : null,
                'score' => $criterion->scale === 'score_1_5' ? $score : null,
                'rationale' => Str::limit(trim((string) ($item['rationale'] ?? '')), 1000),
            ];
        }

        return array_values($rows);
    }

    /** @return array<string, mixed> */
    private function structured(string $model, string $instruction, string $transcript, array $schema): array
    {
        $interaction = $this->client->createInteraction([
            'model' => $model,
            'system_instruction' => $instruction,
            'response_format' => ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema],
            'input' => [['type' => 'user_input', 'content' => [['type' => 'text', 'text' => $transcript]]]],
        ], microtime(true) + self::CALL_SECONDS);

        $text = '';
        foreach ((array) ($interaction['steps'] ?? []) as $step) {
            if (is_array($step) && ($step['type'] ?? null) === 'model_output') {
                foreach ((array) ($step['content'] ?? []) as $part) {
                    $text .= is_array($part) ? (string) ($part['text'] ?? '') : '';
                }
            }
        }

        $json = json_decode($text, true);

        if (! is_array($json)) {
            throw new GeminiException('bad_response', 'Structured output was not valid JSON.');
        }

        return $json;
    }

    private function sameQuestion(string $a, string $b): bool
    {
        $normalise = fn (string $s) => preg_replace('/[^a-z0-9 ]/', '', Str::lower($s));
        similar_text($normalise($a), $normalise($b), $percent);

        return $percent >= 70;
    }
}
