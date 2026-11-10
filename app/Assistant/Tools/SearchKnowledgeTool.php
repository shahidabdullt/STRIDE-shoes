<?php

namespace App\Assistant\Tools;

use App\Assistant\ToolContext;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Support\SearchTerms;
use Illuminate\Support\Str;

class SearchKnowledgeTool implements AssistantTool
{
    public function name(): string
    {
        return 'search_knowledge';
    }

    public function description(): string
    {
        return 'Search Stride\'s FAQs and help articles (shipping, returns, sizing, care, orders, payment). Returns up to 3 matching passages.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'description' => 'The topic or question, e.g. "return window" or "how to measure foot".']],
            'required' => ['query'],
        ];
    }

    public function rules(): array
    {
        return ['query' => ['required', 'string', 'max:200']];
    }

    public function handle(array $args, ToolContext $context): array
    {
        // Any word may match and rows rank by relevance: a question rarely repeats every word of an answer.
        $boolean = SearchTerms::booleanQuery(SearchTerms::words($args['query']), requireAll: false);

        if ($boolean === '') {
            return ['results' => []];
        }

        $faqs = Faq::published()
            ->selectRaw('question, answer, MATCH(question, answer) AGAINST (? IN BOOLEAN MODE) AS score', [$boolean])
            ->whereRaw('MATCH(question, answer) AGAINST (? IN BOOLEAN MODE)', [$boolean])
            ->orderByDesc('score')->limit(3)->get()
            ->map(fn (Faq $f) => ['title' => $f->question, 'excerpt' => Str::limit($f->answer, 400), 'source' => 'faq', 'score' => (float) $f->score]);

        $articles = KnowledgeArticle::published()
            ->selectRaw('title, body, MATCH(title, body) AGAINST (? IN BOOLEAN MODE) AS score', [$boolean])
            ->whereRaw('MATCH(title, body) AGAINST (? IN BOOLEAN MODE)', [$boolean])
            ->orderByDesc('score')->limit(3)->get()
            ->map(fn (KnowledgeArticle $a) => ['title' => $a->title, 'excerpt' => Str::limit($a->body, 400), 'source' => 'article', 'score' => (float) $a->score]);

        $results = $faqs->concat($articles)->sortByDesc('score')->take(3)
            ->map(fn (array $r) => array_diff_key($r, ['score' => true]))->values()->all();

        return ['results' => $results];
    }
}
