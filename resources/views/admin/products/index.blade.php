@php use App\Support\Money; @endphp
<x-layouts.app title="Products">
    <x-admin.nav />

    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Products</h1>
        <x-button :href="route('admin.products.create')">New product</x-button>
    </div>

    <form method="GET" action="{{ route('admin.products.index') }}" class="mb-4 flex gap-2" role="search">
        <div class="flex-1">
            <label for="q" class="sr-only">Search products</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search by name" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">
        </div>
        <x-button variant="secondary" type="submit">Search</x-button>
    </form>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($products as $product)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $product->name }} <span class="font-normal text-stone-600">&middot; {{ $product->color }}</span></p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-stone-600">
                        <span>{{ $product->category->name }}</span>
                        <span>{{ Money::format($product->price_cents) }}</span>
                        <span>{{ (int) $product->stock_total }} in stock</span>
                        @unless ($product->is_active) <x-badge variant="warning">Inactive</x-badge> @endunless
                        @if ($product->is_featured) <x-badge variant="success">Featured</x-badge> @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-button variant="secondary" :href="route('admin.products.edit', $product)">Edit</x-button>
                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-confirm="Delete {{ $product->name }} ({{ $product->color }})?">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" type="submit">Delete</x-button>
                    </form>
                </div>
            </li>
        @empty
            <li class="p-4 text-stone-600">No products found.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.app>
