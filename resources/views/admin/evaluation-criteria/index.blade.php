<x-layouts.app title="Evaluation criteria">
    <x-admin.nav />

    <div class="mb-1 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Evaluation criteria</h1>
        <x-button :href="route('admin.evaluation-criteria.create')">New criterion</x-button>
    </div>
    <p class="mb-4 text-sm text-stone-700">Rubrics the assistant's side of each conversation is checked against after it ends.</p>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($criteria as $criterion)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $criterion->name }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-stone-600">
                        <x-badge>{{ $criterion->scale === 'pass_fail' ? 'pass / fail' : 'score 1–5' }}</x-badge>
                        <span>{{ $criterion->applies_to === 'all' ? 'text and voice' : $criterion->applies_to.' only' }}</span>
                        @unless ($criterion->is_active) <x-badge variant="warning">Inactive</x-badge> @endunless
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-button variant="secondary" :href="route('admin.evaluation-criteria.edit', $criterion)">Edit</x-button>
                    <form method="POST" action="{{ route('admin.evaluation-criteria.destroy', $criterion) }}" data-confirm="Delete {{ $criterion->name }} and all its results?">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" type="submit">Delete</x-button>
                    </form>
                </div>
            </li>
        @empty
            <li class="p-4 text-stone-600">No criteria yet.</li>
        @endforelse
    </ul>
</x-layouts.app>
