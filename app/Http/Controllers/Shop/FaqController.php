<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        return view('faqs.index', [
            'faqsByTopic' => Faq::published()->orderBy('sort')->get()->groupBy('topic'),
            'articles' => KnowledgeArticle::published()->orderBy('title')->get(),
        ]);
    }
}
