<x-layouts.app title="Admin">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">Admin</h1>

    <h2 class="mb-2 text-lg font-semibold">Assistant, last {{ $days }} days</h2>
    <dl class="grid gap-4 sm:grid-cols-3">
        @foreach (['text' => 'Text chat', 'voice' => 'Voice'] as $channel => $label)
            <x-card>
                <dt class="text-sm text-stone-600">{{ $label }} success rate</dt>
                <dd class="mt-1 text-3xl font-bold">{{ $rates[$channel]['rate'] === null ? '—' : $rates[$channel]['rate'].'%' }}</dd>
                <dd class="text-xs text-stone-600">{{ $rates[$channel]['total'] }} analysed conversation(s)</dd>
            </x-card>
        @endforeach
        <x-card>
            <dt class="text-sm text-stone-600">Waiting for analysis</dt>
            <dd class="mt-1 text-3xl font-bold">{{ $pending }}</dd>
            <dd class="text-xs text-stone-600"><a class="underline" href="{{ route('admin.conversations.index', ['outcome' => 'pending']) }}">See pending</a></dd>
        </x-card>
    </dl>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="text-lg font-semibold">Top unanswered topics</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($topTopics as $row)
                    <li class="flex justify-between gap-3"><span>{{ $row->topic }}</span><span class="font-semibold">{{ $row->total }}</span></li>
                @empty
                    <li class="text-stone-600">No unanswered questions.</li>
                @endforelse
            </ul>
            <a href="{{ route('admin.knowledge-gaps.index') }}" class="mt-3 inline-block text-sm font-medium underline">Open knowledge gaps</a>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold">Recent unanswered questions</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($recentFailed as $question)
                    <li>
                        <a class="underline" href="{{ route('admin.conversations.show', $question->conversation_id) }}">{{ $question->question }}</a>
                        <span class="text-stone-600">&middot; {{ $question->topic }} &middot; {{ $question->conversation?->channel }}</span>
                    </li>
                @empty
                    <li class="text-stone-600">None.</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    <h2 class="mt-8 mb-2 text-lg font-semibold">Store</h2>
    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($counts as $label => $count)
            <x-card>
                <dt class="text-sm text-stone-600">{{ $label }}</dt>
                <dd class="mt-1 text-3xl font-bold">{{ $count }}</dd>
            </x-card>
        @endforeach
    </dl>

    <p class="mt-6 text-sm text-stone-700">
        Active AI config:
        @if ($activeConfig)
            version {{ $activeConfig->version }} ({{ $activeConfig->text_model }})
        @else
            none yet. <a class="underline" href="{{ route('admin.ai-config.index') }}">Create one</a>.
        @endif
    </p>
</x-layouts.app>
