<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationCriterion> */
class EvaluationCriterionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Only quoted catalog prices',
            'rubric' => 'Pass if every price the assistant states appears in a tool result.',
            'scale' => 'pass_fail',
            'applies_to' => 'all',
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
