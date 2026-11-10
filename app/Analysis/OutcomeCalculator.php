<?php

namespace App\Analysis;

/**
 * The conversation outcome, computed in PHP and never by the model (BLUEPRINT §2.6, §1.2 #1):
 * failed if a technical error stopped a reply or any in-scope question was not answered; otherwise success.
 * Off-topic questions (`out_of_scope`) never fail a conversation.
 */
class OutcomeCalculator
{
    /**
     * @param  iterable<object{outcome: string}>  $questions
     * @return array{outcome: 'success'|'failed', failure_kind: ?string, questions_total: int, questions_unanswered: int}
     */
    public static function compute(bool $hadTechnicalError, iterable $questions): array
    {
        $total = 0;
        $unanswered = 0;

        foreach ($questions as $question) {
            $total++;
            $unanswered += $question->outcome === 'not_answered' ? 1 : 0;
        }

        $knowledgeGap = $unanswered > 0;

        return [
            'outcome' => $hadTechnicalError || $knowledgeGap ? 'failed' : 'success',
            'failure_kind' => match (true) {
                $hadTechnicalError && $knowledgeGap => 'both',
                $hadTechnicalError => 'technical',
                $knowledgeGap => 'knowledge_gap',
                default => null,
            },
            'questions_total' => $total,
            'questions_unanswered' => $unanswered,
        ];
    }
}
