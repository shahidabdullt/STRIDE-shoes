<x-layouts.app title="Shoes">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Shoes</h1>
        <x-button variant="secondary" data-drawer-open="filters" class="lg:hidden" aria-controls="filters">Filters</x-button>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            <p class="font-medium">Some filters were not valid and were ignored:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-4 lg:grid lg:grid-cols-[16rem_1fr] lg:gap-8">
        <x-drawer id="filters" title="Filters">
            <form method="GET" action="{{ route('products.index') }}" class="space-y-4">
                <x-input name="q" label="Search" type="search" :value="$filters['q'] ?? null" maxlength="100" />

                <div>
                    <label for="category" class="mb-1 block text-sm font-medium">Category</label>
                    <select id="category" name="category" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected(($filters['category'] ?? null) === $category->slug)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="activity" class="mb-1 block text-sm font-medium">Activity</label>
                    <select id="activity" name="activity" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                        <option value="">Any activity</option>
                        @foreach (['running', 'trail', 'walking', 'casual', 'training'] as $activity)
                            <option value="{{ $activity }}" @selected(($filters['activity'] ?? null) === $activity)>{{ ucfirst($activity) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="audience" class="mb-1 block text-sm font-medium">For</label>
                    <select id="audience" name="audience" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                        <option value="">Everyone</option>
                        @foreach (['men' => 'Men', 'women' => 'Women', 'unisex' => 'Unisex'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['audience'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="size" class="mb-1 block text-sm font-medium">Size in stock (US)</label>
                    <select id="size" name="size" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                        <option value="">Any size</option>
                        @foreach ($sizes as $size)
                            <option value="{{ $size }}" @selected((string) ($filters['size'] ?? '') !== '' && (float) $filters['size'] === (float) $size)>{{ \App\Support\Money::size($size) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-input name="min_price" label="Min price" type="number" inputmode="numeric" min="0" max="1000" :value="$filters['min_price'] ?? null" />
                    <x-input name="max_price" label="Max price" type="number" inputmode="numeric" min="0" max="1000" :value="$filters['max_price'] ?? null" />
                </div>

                <div>
                    <label for="sort" class="mb-1 block text-sm font-medium">Sort by</label>
                    <select id="sort" name="sort" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                        <option value="">Featured</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>Price: low to high</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>Price: high to low</option>
                        <option value="newest" @selected(($filters['sort'] ?? null) === 'newest')>Newest</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <x-button type="submit" class="flex-1">Apply</x-button>
                    <x-button variant="secondary" :href="route('products.index')">Clear</x-button>
                </div>
            </form>
        </x-drawer>

        <section aria-label="Results" class="mt-6 lg:mt-0">
            <p class="mb-3 text-sm text-stone-600">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('shoe', $products->total()) }}</p>

            @if ($products->isEmpty())
                <x-card>
                    <p class="font-medium">No shoes match those filters.</p>
                    <p class="mt-1 text-stone-700">Try removing a filter, or <a class="underline" href="{{ route('products.index') }}">see all shoes</a>.</p>
                </x-card>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                <div class="mt-6">{{ $products->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
