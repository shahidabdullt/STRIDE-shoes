@props(['product'])

<a href="{{ route('products.show', $product) }}" class="group block overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
    <x-product-image :product="$product" />
    <div class="p-3">
        <p class="text-xs text-stone-600">{{ $product->category?->name }}</p>
        <h3 class="mt-0.5 font-semibold group-hover:underline">{{ $product->name }}</h3>
        <p class="text-sm text-stone-600">{{ $product->color }}</p>
        <p class="mt-1 font-semibold">{{ \App\Support\Money::format($product->price_cents) }}</p>
    </div>
</a>
