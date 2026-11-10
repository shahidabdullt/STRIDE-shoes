<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeGapFilterRequest;
use App\Models\ConversationQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Questions the assistant could not answer, grouped by topic, with the "Create FAQ from this" loop (AC-A8). */
class KnowledgeGapController extends Controller
{
    public function index(KnowledgeGapFilterRequest $request): View
    {
        $filters = $request->validated();
        $resolved = ($filters['resolved'] ?? 'no') === 'yes';

        $questions = ConversationQuestion::query()
            ->with('conversation:id,uuid,channel,started_at')
            ->where('outcome', 'not_answered')
            ->when($resolved, fn (Builder $q) => $q->whereNotNull('resolved_at'), fn (Builder $q) => $q->whereNull('resolved_at'))
            ->when($filters['channel'] ?? null, fn (Builder $q, string $v) => $q->whereHas('conversation', fn (Builder $c) => $c->where('channel', $v)))
            ->when($filters['from'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->latest('id')
            ->limit(500)
            ->get();

        $topics = $questions->groupBy('topic')
            ->map(fn ($items, $topic) => [
                'topic' => $topic,
                'count' => $items->count(),
                'last_asked' => $items->max('created_at'),
                'questions' => $items,
            ])
            ->sortByDesc('count')->values();

        return view('admin.knowledge-gaps.index', ['topics' => $topics, 'filters' => $filters, 'resolved' => $resolved]);
    }

    /** Opens the FAQ form pre-filled from the question; saving it resolves the gap. */
    public function faq(ConversationQuestion $question): RedirectResponse
    {
        return redirect()->route('admin.faqs.create', ['gap' => $question->id]);
    }

    public function resolve(Request $request, ConversationQuestion $question): RedirectResponse
    {
        $faqId = $request->validate(['faq_id' => ['nullable', 'integer', 'exists:faqs,id']])['faq_id'] ?? null;

        $question->forceFill(['resolved_at' => now(), 'resolved_by_faq_id' => $faqId])->save();

        return back()->with('status', 'Marked as resolved.');
    }
}
