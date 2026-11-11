<?php

namespace Tests\Unit;

use App\Analysis\OutcomeCalculator;
use PHPUnit\Framework\TestCase;

/** The conversation outcome rules (BLUEPRINT §2.6, §9.1). Pure PHP: no framework or database needed. */
class OutcomeCalculatorTest extends TestCase
{
    /** @return list<object{outcome: string}> */
    private function questions(string ...$outcomes): array
    {
        return array_map(fn (string $outcome) => (object) ['outcome' => $outcome], $outcomes);
    }

    public function test_answered_and_off_topic_questions_are_a_success(): void
    {
        $result = OutcomeCalculator::compute(false, $this->questions('answered', 'out_of_scope'));

        $this->assertSame(['success', null, 2, 0], array_values($result));
    }

    public function test_only_off_topic_questions_is_a_success(): void
    {
        $this->assertSame('success', OutcomeCalculator::compute(false, $this->questions('out_of_scope'))['outcome']);
    }

    public function test_an_unanswered_question_fails_as_a_knowledge_gap(): void
    {
        $result = OutcomeCalculator::compute(false, $this->questions('answered', 'not_answered'));

        $this->assertSame(['failed', 'knowledge_gap', 2, 1], array_values($result));
    }

    public function test_a_technical_error_fails_even_when_everything_was_answered(): void
    {
        $this->assertSame(['failed', 'technical'], array_slice(array_values(OutcomeCalculator::compute(true, $this->questions('answered'))), 0, 2));
    }

    public function test_both_kinds_of_failure_together(): void
    {
        $this->assertSame(['failed', 'both'], array_slice(array_values(OutcomeCalculator::compute(true, $this->questions('not_answered'))), 0, 2));
    }

    public function test_no_questions_and_no_errors_is_a_success(): void
    {
        $this->assertSame(['success', null, 0, 0], array_values(OutcomeCalculator::compute(false, [])));
    }
}
