<x-layouts.app title="Data points">
    <x-admin.nav />

    <div class="mb-1 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Data points</h1>
        <x-button :href="route('admin.data-points.create')">New data point</x-button>
    </div>
    <p class="mb-4 text-sm text-stone-700">Facts extracted from each conversation after it ends, for example the customer's shoe size.</p>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($definitions as $definition)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $definition->label }} <span class="font-mono text-sm font-normal text-stone-600">{{ $definition->key }}</span></p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-stone-600">
                        <x-badge>{{ $definition->type }}</x-badge>
                        @if ($definition->type === 'enum') <span>{{ implode(', ', $definition->options ?? []) }}</span> @endif
                        <span>{{ $definition->values_count }} value(s)</span>
                        @unless ($definition->is_active) <x-badge variant="warning">Inactive</x-badge> @endunless
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-button variant="secondary" :href="route('admin.data-points.edit', $definition)">Edit</x-button>
                    <form method="POST" action="{{ route('admin.data-points.destroy', $definition) }}" data-confirm="Delete {{ $definition->label }} and all its extracted values?">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" type="submit">Delete</x-button>
                    </form>
                </div>
            </li>
        @empty
            <li class="p-4 text-stone-600">No data points yet.</li>
        @endforelse
    </ul>
</x-layouts.app>
