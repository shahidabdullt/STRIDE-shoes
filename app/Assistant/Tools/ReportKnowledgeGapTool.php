<?php

namespace App\Assistant\Tools;

use App\Assistant\ToolContext;

class ReportKnowledgeGapTool implements AssistantTool
{
    public function name(): string
    {
        return 'report_knowledge_gap';
    }

    public function description(): string
    {
        return 'Record a customer question about Stride that you could not answer from your tools or the store information you were given. Call it before telling the customer you don\'t know.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'question' => ['type' => 'string', 'description' => 'The customer\'s question, in their words.'],
                'topic' => ['type' => 'string', 'description' => 'One or two words, e.g. "shipping", "sizing", "gift cards".'],
            ],
            'required' => ['question', 'topic'],
        ];
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'topic' => ['required', 'string', 'max:60'],
        ];
    }

    public function handle(array $args, ToolContext $context): array
    {
        $conversation = $context->conversation;

        if ($conversation->questions()->where('detected_by', 'model_report')->count() >= config('assistant.max_gap_reports')) {
            return ['recorded' => false, 'reason' => 'Report limit reached for this conversation.'];
        }

        $conversation->questions()->create([
            'question' => $args['question'],
            'topic' => mb_strtolower(trim($args['topic'])),
            'outcome' => 'not_answered',
            'source' => 'none',
            'reason' => 'Reported by the assistant during the conversation.',
            'detected_by' => 'model_report',
        ]);

        return ['recorded' => true];
    }
}
