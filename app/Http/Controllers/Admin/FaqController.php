<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\ConversationQuestion;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('admin.faqs.index', [
            'faqs' => Faq::orderBy('topic')->orderBy('sort')->orderBy('id')->paginate(25),
        ]);
    }

    /** `?gap={question}` pre-fills the form from a knowledge gap ("Create FAQ from this"). */
    public function create(Request $request): View
    {
        $gap = $request->integer('gap') ? ConversationQuestion::find($request->integer('gap')) : null;

        return view('admin.faqs.form', [
            'faq' => new Faq(['is_published' => true, 'sort' => 0, 'question' => $gap?->question, 'topic' => $gap?->topic]),
            'gap' => $gap,
        ]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        $faq = Faq::create($request->attributesForSave());

        // Created from a knowledge gap: resolve it and go back to the gaps page (AC-A8).
        if ($gapId = $request->validated('from_gap')) {
            ConversationQuestion::whereKey($gapId)->update(['resolved_at' => now(), 'resolved_by_faq_id' => $faq->id]);

            return redirect()->route('admin.knowledge-gaps.index')->with('status', 'FAQ created and the gap marked as resolved.');
        }

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ created.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.form', ['faq' => $faq]);
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->attributesForSave());

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ saved.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ deleted.');
    }
}
