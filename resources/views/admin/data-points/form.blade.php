@php $editing = $definition->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit data point' : 'New data point'">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $editing ? 'Edit data point' : 'New data point' }}</h1>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $editing ? route('admin.data-points.update', $definition) : route('admin.data-points.store') }}" class="space-y-4">
            @csrf
            @if ($editing) @method('PUT') @endif
            <x-input name="label" label="Label" :value="$definition->label" required />
            <x-input name="key" label="Key (lower case, e.g. shoe_size)" :value="$definition->key" required />
            <x-textarea name="instruction" label="How to extract it" :value="$definition->instruction" rows="3" hint="For example: The US shoe size the customer says they wear." required />
            <x-select name="type" label="Type" :value="$definition->type" :options="['string' => 'Text', 'number' => 'Number', 'boolean' => 'Yes / no', 'enum' => 'One of a list']" />
            <x-input name="options" label="Options (only for “One of a list”, comma-separated)" :value="implode(', ', $definition->options ?? [])" />
            <x-input name="sort" label="Sort order" type="number" min="0" :value="$definition->sort" />
            <x-checkbox name="is_active" label="Active (extracted from new conversations)" :checked="$definition->is_active" />
            <div class="flex gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" :href="route('admin.data-points.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
