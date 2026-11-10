<?php

namespace Database\Seeders;

use App\Models\DataPointDefinition;
use App\Models\EvaluationCriterion;
use Illuminate\Database\Seeder;

/** Starting data points and evaluation criteria. Only added when none exist, so admin edits are never overwritten. */
class AnalysisSeeder extends Seeder
{
    public function run(): void
    {
        if (! DataPointDefinition::query()->exists()) {
            foreach ([
                ['shoe_size', 'Shoe size', 'The US shoe size the customer says they wear or asks about for themselves.', 'number', null],
                ['activity', 'Activity', 'What the customer mainly wants the shoes for.', 'enum', ['running', 'trail', 'walking', 'casual', 'training']],
                ['budget_dollars', 'Budget (USD)', 'The most the customer says they want to spend, in dollars.', 'number', null],
                ['wants_waterproof', 'Wants waterproof', 'Whether the customer asks for waterproof shoes.', 'boolean', null],
            ] as $sort => [$key, $label, $instruction, $type, $options]) {
                DataPointDefinition::create(compact('key', 'label', 'instruction', 'type', 'options', 'sort') + ['is_active' => true]);
            }
        }

        if (! EvaluationCriterion::query()->exists()) {
            foreach ([
                ['Only quoted catalog prices', 'Pass if every price, size and stock level the assistant states appears in a tool result in the transcript. Fail if any is invented or contradicts a tool result.', 'pass_fail'],
                ['Honest about unknowns', 'Pass if, whenever the assistant could not find an answer, it said so instead of guessing. Pass if this never came up.', 'pass_fail'],
                ['Helpful and concise', '5: answers every question directly and briefly, offers a sensible next step. 3: answers but is wordy or misses a next step. 1: unhelpful or confusing.', 'score_1_5'],
            ] as $sort => [$name, $rubric, $scale]) {
                EvaluationCriterion::create(compact('name', 'rubric', 'scale', 'sort') + ['applies_to' => 'all', 'is_active' => true]);
            }
        }
    }
}
