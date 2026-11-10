<x-layouts.app title="FAQs">
    <x-admin.nav />

    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">FAQs</h1>
        <x-button :href="route('admin.faqs.create')">New FAQ</x-button>
    </div>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($faqs as $faq)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $faq->question }}</p>
                    <p class="mt-1 flex items-center gap-2 text-sm text-stone-600">
                        <x-badge>{{ $faq->topic }}</x-badge>
                        @unless ($faq->is_published) <x-badge variant="warning">Draft</x-badge> @endunless
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-button variant="secondary" :href="route('admin.faqs.edit', $faq)">Edit</x-button>
                    <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="Delete this FAQ?">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" type="submit">Delete</x-button>
                    </form>
                </div>
            </li>
        @empty
            <li class="p-4 text-stone-600">No FAQs yet.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $faqs->links() }}</div>
</x-layouts.app>
