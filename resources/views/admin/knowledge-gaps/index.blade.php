<x-layouts.app title="Knowledge gaps">
    <x-admin.nav />
    <h1 class="mb-1 text-2xl font-bold tracking-tight">Knowledge gaps</h1>
    <p class="mb-4 text-sm text-stone-700">Questions the assistant could not answer from the catalog or the knowledge base. Answer them with an FAQ so it can next time.</p>

    <form method="GET" action="{{ route('admin.knowledge-gaps.index') }}" class="mb-4 grid gap-3 rounded-xl border border-stone-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5" role="search">
        <x-select name="resolved" label="Show" :value="$filters['resolved'] ?? 'no'" :options="['no' => 'Unresolved', 'yes' => 'Resolved']" />
        <x-select name="channel" label="Channel" :value="$filters['channel'] ?? ''" :options="['' => 'Any', 'text' => 'Text', 'voice' => 'Voice']" />
        <x-input name="from" label="From" type="date" :value="$filters['from'] ?? ''" />
        <x-input name="to" label="To" type="date" :value="$filters['to'] ?? ''" />
        <div class="flex items-end gap-2">
            <x-button type="submit">Filter</x-button>
        </div>
    </form>

    @forelse ($topics as $group)
        <section class="mb-4 rounded-xl border border-stone-200 bg-white">
            <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-200 px-4 py-3">
                <h2 class="text-lg font-semibold">{{ $group['topic'] }}</h2>
                <p class="text-sm text-stone-600">{{ $group['count'] }} question(s) &middot; last asked {{ $group['last_asked']?->diffForHumans() }}</p>
            </header>
            <ul class="divide-y divide-stone-200">
                @foreach ($group['questions'] as $question)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p>{{ $question->question }}</p>
                            <p class="mt-1 text-xs text-stone-600">
                                {{ $question->created_at?->format('j M Y') }} &middot; {{ $question->conversation?->channel }} &middot;
                                <a class="underline" href="{{ route('admin.conversations.show', $question->conversation_id) }}">conversation</a>
                                @if ($question->resolved_by_faq_id) &middot; answered by <a class="underline" href="{{ route('admin.faqs.edit', $question->resolved_by_faq_id) }}">FAQ #{{ $question->resolved_by_faq_id }}</a> @endif
                            </p>
                        </div>
                        @unless ($resolved)
                            <div class="flex gap-2">
                                <x-button :href="route('admin.knowledge-gaps.faq', $question)">Create FAQ from this</x-button>
                                <form method="POST" action="{{ route('admin.knowledge-gaps.resolve', $question) }}">
                                    @csrf
                                    <x-button variant="secondary" type="submit">Mark resolved</x-button>
                                </form>
                            </div>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="rounded-xl border border-stone-200 bg-white p-4 text-stone-600">{{ $resolved ? 'No resolved gaps yet.' : 'No unanswered questions.' }}</p>
    @endforelse
</x-layouts.app>
