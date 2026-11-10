@php $editing = $article->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit article' : 'New article'">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $editing ? 'Edit article' : 'New article' }}</h1>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $editing ? route('admin.knowledge.update', $article) : route('admin.knowledge.store') }}" class="space-y-4">
            @csrf
            @if ($editing) @method('PUT') @endif
            <x-input name="title" label="Title" :value="$article->title" required />
            <x-input name="slug" label="URL slug (leave blank to generate)" :value="$article->slug" />
            <x-textarea name="body" label="Body" :value="$article->body" rows="10" required />
            <x-checkbox name="is_published" label="Published" :checked="$article->is_published" />
            <x-checkbox name="always_include" label="Always include in the assistant's prompt (keep these short: the total is capped)" :checked="$article->always_include" />
            <div class="flex gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" :href="route('admin.knowledge.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
