<x-layouts.app>
    <section class="max-w-2xl">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Shoes for every stride</h1>
        <p class="mt-3 text-lg text-stone-700">
            Running, trail, walking, casual and training shoes in US sizes. Find your pair in a few taps.
        </p>
        <x-button :href="route('products.index')" class="mt-5">Shop all shoes</x-button>
    </section>

    <section class="mt-10" aria-labelledby="categories-heading">
        <h2 id="categories-heading" class="text-xl font-semibold">Shop by category</h2>
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <li>
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" class="inline-flex min-h-11 items-center rounded-full border border-stone-300 bg-white px-4 text-sm font-medium hover:bg-stone-100">
                        {{ $category->name }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($featured->isNotEmpty())
        <section class="mt-10" aria-labelledby="featured-heading">
            <h2 id="featured-heading" class="text-xl font-semibold">Featured</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($featured as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
