<x-layouts.app title="Knowledge">
    <x-admin.nav />

    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Knowledge articles</h1>
        <x-button :href="route('admin.knowledge.create')">New article</x-button>
    </div>

    <p class="mb-4 text-sm text-stone-700">
        Always-included articles are added to every assistant prompt: {{ $alwaysWords }} of {{ $wordLimit }} words used.
    </p>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($articles as $article)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $article->title }}</p>
                    <p class="mt-1 flex items-center gap-2 text-sm text-stone-600">
                        <span>{{ str_word_count($article->body) }} words</span>
                        @if ($article->always_include) <x-badge variant="success">Always included</x-badge> @endif
                        @unless ($article->is_published) <x-badge variant="warning">Draft</x-badge> @endunless
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-button variant="secondary" :href="route('admin.knowledge.edit', $article)">Edit</x-button>
                    <form method="POST" action="{{ route('admin.knowledge.destroy', $article) }}" data-confirm="Delete {{ $article->title }}?">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" type="submit">Delete</x-button>
                    </form>
                </div>
            </li>
        @empty
            <li class="p-4 text-stone-600">No articles yet.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $articles->links() }}</div>
</x-layouts.app>
