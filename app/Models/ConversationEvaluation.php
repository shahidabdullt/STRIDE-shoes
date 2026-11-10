<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'evaluation_criterion_id', 'passed', 'score', 'rationale', 'evaluator_model'])]
class ConversationEvaluation extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['passed' => 'boolean', 'score' => 'integer'];
    }

    /** @return BelongsTo<EvaluationCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }
}
