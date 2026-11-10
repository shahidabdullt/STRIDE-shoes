@php
    $links = [
        'admin.dashboard' => ['Dashboard', 'admin.dashboard'],
        'admin.conversations.index' => ['Conversations', 'admin.conversations.*'],
        'admin.knowledge-gaps.index' => ['Knowledge gaps', 'admin.knowledge-gaps.*'],
        'admin.products.index' => ['Products', 'admin.products.*'],
        'admin.categories.index' => ['Categories', 'admin.categories.*'],
        'admin.faqs.index' => ['FAQs', 'admin.faqs.*'],
        'admin.knowledge.index' => ['Knowledge', 'admin.knowledge.*'],
        'admin.ai-config.index' => ['AI config', 'admin.ai-config.*'],
        'admin.data-points.index' => ['Data points', 'admin.data-points.*'],
        'admin.evaluation-criteria.index' => ['Evaluations', 'admin.evaluation-criteria.*'],
        'admin.orders.index' => ['Orders', 'admin.orders.*'],
    ];
@endphp

<nav aria-label="Admin sections" class="-mx-4 mb-6 overflow-x-auto border-b border-stone-200 px-4">
    <ul class="flex gap-1 whitespace-nowrap">
        @foreach ($links as $route => [$label, $pattern])
            <li>
                <a
                    href="{{ route($route) }}"
                    @if (request()->routeIs($pattern)) aria-current="page" @endif
                    @class([
                        'inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-medium',
                        'border-emerald-700 text-emerald-800' => request()->routeIs($pattern),
                        'border-transparent text-stone-700 hover:text-stone-900' => ! request()->routeIs($pattern),
                    ])
                >{{ $label }}</a>
            </li>
        @endforeach
        <li class="sm:hidden">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium text-stone-700 hover:text-stone-900">Sign out</button>
            </form>
        </li>
    </ul>
</nav>
