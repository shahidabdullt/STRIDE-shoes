<x-layouts.app title="Categories">
    <x-admin.nav />

    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Categories</h1>
        <x-button :href="route('admin.categories.create')">New category</x-button>
    </div>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($categories as $category)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $category->name }}</p>
                    <p class="text-sm text-stone-600">/{{ $category->slug }} &middot; {{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <x-button variant="secondary" :href="route('admin.categories.edit', $category)">Edit</x-button>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Delete the category {{ $category->name }}?">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" type="submit" :disabled="$category->products_count > 0">Delete</x-button>
                    </form>
                </div>
            </li>
        @empty
            <li class="p-4 text-stone-600">No categories yet.</li>
        @endforelse
    </ul>
</x-layouts.app>
