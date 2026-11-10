<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeArticleRequest;
use App\Models\KnowledgeArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgeArticleController extends Controller
{
    public function index(): View
    {
        $always = KnowledgeArticle::where('always_include', true)->where('is_published', true)->pluck('body');

        return view('admin.knowledge.index', [
            'articles' => KnowledgeArticle::orderByDesc('always_include')->orderBy('title')->paginate(25),
            'alwaysWords' => $always->sum(fn (string $body) => str_word_count($body)),
            'wordLimit' => config('assistant.always_include_max_words'),
        ]);
    }

    public function create(): View
    {
        return view('admin.knowledge.form', ['article' => new KnowledgeArticle(['is_published' => true])]);
    }

    public function store(KnowledgeArticleRequest $request): RedirectResponse
    {
        KnowledgeArticle::create($request->validated());

        return redirect()->route('admin.knowledge.index')->with('status', 'Article created.');
    }

    public function edit(KnowledgeArticle $article): View
    {
        return view('admin.knowledge.form', ['article' => $article]);
    }

    public function update(KnowledgeArticleRequest $request, KnowledgeArticle $article): RedirectResponse
    {
        $article->update($request->validated());

        return redirect()->route('admin.knowledge.index')->with('status', 'Article saved.');
    }

    public function destroy(KnowledgeArticle $article): RedirectResponse
    {
        $article->delete();

        return redirect()->route('admin.knowledge.index')->with('status', 'Article deleted.');
    }
}
