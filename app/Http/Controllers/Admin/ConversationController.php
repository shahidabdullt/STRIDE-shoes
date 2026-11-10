<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConversationFilterRequest;
use App\Http\Requests\Admin\OutcomeOverrideRequest;
use App\Jobs\AnalyzeConversation;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(ConversationFilterRequest $request): View
    {
        $filters = $request->validated();

        $conversations = Conversation::query()
            ->withCount(['questions as unanswered_count' => fn (Builder $q) => $q->where('outcome', 'not_answered')])
            ->when($filters['outcome'] ?? null, fn (Builder $q, string $v) => $q->where('outcome', $v))
            ->when($filters['failure_kind'] ?? null, fn (Builder $q, string $v) => $q->whereIn('failure_kind', [$v, 'both']))
            ->when($filters['channel'] ?? null, fn (Builder $q, string $v) => $q->where('channel', $v))
            ->when($filters['device_class'] ?? null, fn (Builder $q, string $v) => $q->where('device_class', $v))
            ->when($filters['from'] ?? null, fn (Builder $q, string $v) => $q->where('started_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, string $v) => $q->where('started_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($filters['q'] ?? null, fn (Builder $q, string $v) => $q->whereHas(
                'questions',
                fn (Builder $questions) => $questions->where('question', 'like', '%'.addcslashes($v, '%_\\').'%'),
            ))
            ->latest('started_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.conversations.index', ['conversations' => $conversations, 'filters' => $filters]);
    }

    public function show(Conversation $conversation): View
    {
        $conversation->load(['messages', 'questions', 'dataPoints.definition', 'evaluations.criterion', 'aiConfigVersion', 'outcomeOverrider']);

        return view('admin.conversations.show', ['conversation' => $conversation]);
    }

    public function analyze(Conversation $conversation): RedirectResponse
    {
        if ($conversation->isActive()) {
            return back()->with('error', 'The conversation is still active. It is analysed when it ends.');
        }

        $conversation->forceFill(['analysis_status' => 'pending', 'analysis_error' => null])->save();
        AnalyzeConversation::dispatch($conversation);

        return back()->with('status', 'Analysis queued. Refresh in a minute (the queue worker must be running).');
    }

    public function outcome(OutcomeOverrideRequest $request, Conversation $conversation): RedirectResponse
    {
        if ($request->validated('outcome') === 'automatic') {
            $conversation->forceFill(['outcome_overridden_by' => null])->save();
            AnalyzeConversation::computeOutcome($conversation);

            return back()->with('status', 'Override removed: the outcome is computed automatically again.');
        }

        $conversation->forceFill([
            'outcome' => $request->validated('outcome'),
            'outcome_overridden_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Outcome overridden.');
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        // Messages, questions, data points and evaluations cascade (AC-A6).
        $conversation->delete();

        return redirect()->route('admin.conversations.index')->with('status', 'Conversation deleted.');
    }
}
