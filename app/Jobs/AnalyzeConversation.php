<?php

namespace App\Jobs;

use App\Analysis\ConversationAnalyzer;
use App\Analysis\OutcomeCalculator;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/** Runs after a conversation ends (BLUEPRINT §2.6). Safe to re-run: it replaces its own previous results. */
#[Tries(3)]
#[Backoff(30, 120)]
#[Timeout(240)]
class AnalyzeConversation implements ShouldQueue
{
    use Queueable;

    public function __construct(public Conversation $conversation) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->conversation->id))->expireAfter(300)];
    }

    public function handle(ConversationAnalyzer $analyzer): void
    {
        $conversation = $this->conversation->fresh(['messages', 'aiConfigVersion']);

        if ($conversation === null) {
            return;
        }

        if (! $conversation->messages->contains('role', 'user')) {
            $conversation->forceFill(['analysis_status' => 'skipped', 'analysis_error' => null])->save();

            return;
        }

        $conversation->forceFill(['analysis_status' => 'running'])->save();

        $problems = $analyzer->analyze($conversation);
        $this->computeOutcome($conversation);

        $conversation->forceFill([
            'analysis_status' => 'done',
            'analysis_error' => $problems === [] ? null : 'Partly analysed: '.implode('; ', $problems),
        ])->save();
    }

    /** A manual override by an admin is never replaced. */
    public static function computeOutcome(Conversation $conversation): void
    {
        $result = OutcomeCalculator::compute(
            $conversation->messages()->where('role', 'assistant')->where('status', 'error')->exists(),
            $conversation->questions()->get(),
        );

        if ($conversation->outcome_overridden_by !== null) {
            unset($result['outcome']);
        }

        $conversation->forceFill($result)->save();
    }

    public function failed(?Throwable $exception): void
    {
        $conversation = $this->conversation->fresh();

        if ($conversation === null) {
            return;
        }

        $conversation->forceFill([
            'analysis_status' => 'failed',
            'analysis_error' => mb_substr($exception?->getMessage() ?? 'Unknown error', 0, 500),
        ])->save();

        // Without the classifier a conversation can still be known to have failed (an error reply or a live
        // gap report), but never known to have succeeded: in that case the outcome stays pending.
        $result = OutcomeCalculator::compute(
            $conversation->messages()->where('role', 'assistant')->where('status', 'error')->exists(),
            $conversation->questions()->get(),
        );

        if ($result['outcome'] === 'failed' && $conversation->outcome_overridden_by === null) {
            $conversation->forceFill($result)->save();
        }
    }
}
