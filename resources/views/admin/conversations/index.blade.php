<x-layouts.app title="Conversations">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">Conversations</h1>

    <form method="GET" action="{{ route('admin.conversations.index') }}" class="mb-4 grid gap-3 rounded-xl border border-stone-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" role="search">
        <x-select name="outcome" label="Outcome" :value="$filters['outcome'] ?? ''" :options="['' => 'Any', 'success' => 'Success', 'failed' => 'Failed', 'pending' => 'Pending']" />
        <x-select name="failure_kind" label="Failure kind" :value="$filters['failure_kind'] ?? ''" :options="['' => 'Any', 'knowledge_gap' => 'Knowledge gap', 'technical' => 'Technical']" />
        <x-select name="channel" label="Channel" :value="$filters['channel'] ?? ''" :options="['' => 'Any', 'text' => 'Text', 'voice' => 'Voice']" />
        <x-select name="device_class" label="Device" :value="$filters['device_class'] ?? ''" :options="['' => 'Any', 'mobile' => 'Mobile', 'desktop' => 'Desktop']" />
        <x-input name="from" label="From" type="date" :value="$filters['from'] ?? ''" />
        <x-input name="to" label="To" type="date" :value="$filters['to'] ?? ''" />
        <x-input name="q" label="Question contains" type="search" :value="$filters['q'] ?? ''" maxlength="100" />
        <div class="flex items-end gap-2">
            <x-button type="submit">Filter</x-button>
            <x-button variant="secondary" :href="route('admin.conversations.index')">Clear</x-button>
        </div>
    </form>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($conversations as $conversation)
            <li>
                <a href="{{ route('admin.conversations.show', $conversation) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 hover:bg-stone-50">
                    <span class="w-40 shrink-0 text-sm tabular-nums text-stone-700">{{ $conversation->started_at->format('j M Y, H:i') }}</span>
                    <x-admin.outcome-badge :outcome="$conversation->outcome" />
                    <span class="text-sm">{{ ucfirst($conversation->channel) }}{{ $conversation->voice_mode ? ' ('.str_replace('_', '-', $conversation->voice_mode).')' : '' }} &middot; {{ $conversation->device_class }}</span>
                    <span class="text-sm text-stone-600">{{ $conversation->message_count }} messages</span>
                    @if ($conversation->unanswered_count)
                        <span class="text-sm text-red-800">{{ $conversation->unanswered_count }} unanswered</span>
                    @endif
                    @if ($conversation->failure_kind)
                        <x-badge variant="warning">{{ str_replace('_', ' ', $conversation->failure_kind) }}</x-badge>
                    @endif
                    @if ($conversation->status === 'active')
                        <x-badge>active</x-badge>
                    @endif
                </a>
            </li>
        @empty
            <li class="p-4 text-stone-600">No conversations match.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $conversations->links() }}</div>
</x-layouts.app>
