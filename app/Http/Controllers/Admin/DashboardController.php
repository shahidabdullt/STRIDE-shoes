<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConfigVersion;
use App\Models\Conversation;
use App\Models\ConversationQuestion;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const DAYS = 30;

    public function __invoke(): View
    {
        $since = now()->subDays(self::DAYS);

        // Success rate per channel over analysed conversations only (pending ones are not counted).
        $rates = Conversation::query()
            ->where('started_at', '>=', $since)
            ->whereIn('outcome', ['success', 'failed'])
            ->select('channel', DB::raw('COUNT(*) as total'), DB::raw("SUM(outcome = 'success') as successes"))
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');

        return view('admin.dashboard', [
            'counts' => [
                'Products' => Product::count(),
                'FAQs' => Faq::count(),
                'Knowledge articles' => KnowledgeArticle::count(),
                'Orders' => Order::count(),
            ],
            'days' => self::DAYS,
            'rates' => collect(['text', 'voice'])->mapWithKeys(fn ($channel) => [$channel => [
                'total' => (int) ($rates[$channel]->total ?? 0),
                'rate' => ($rates[$channel]->total ?? 0) > 0 ? round(100 * $rates[$channel]->successes / $rates[$channel]->total) : null,
            ]]),
            'pending' => Conversation::where('started_at', '>=', $since)->where('outcome', 'pending')->count(),
            'topTopics' => ConversationQuestion::query()
                ->where('outcome', 'not_answered')->whereNull('resolved_at')->where('created_at', '>=', $since)
                ->select('topic', DB::raw('COUNT(*) as total'))
                ->groupBy('topic')->orderByDesc('total')->limit(5)->get(),
            'recentFailed' => ConversationQuestion::query()
                ->with('conversation:id,channel')
                ->where('outcome', 'not_answered')->whereNull('resolved_at')
                ->latest('id')->limit(5)->get(),
            'activeConfig' => AiConfigVersion::active(),
        ]);
    }
}
