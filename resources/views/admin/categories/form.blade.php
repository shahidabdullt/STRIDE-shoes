@php $editing = $category->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit category' : 'New category'">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $editing ? 'Edit category' : 'New category' }}</h1>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="space-y-4">
            @csrf
            @if ($editing) @method('PUT') @endif
            <x-input name="name" label="Name" :value="$category->name" required />
            <x-input name="slug" label="URL slug (leave blank to generate)" :value="$category->slug" />
            <x-textarea name="description" label="Description" :value="$category->description" rows="3" />
            <x-input name="sort" label="Sort order" type="number" min="0" :value="$category->sort" />
            <div class="flex gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" :href="route('admin.categories.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
