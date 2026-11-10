@php
    use App\Support\Money;
    $attrs = $product->attributes ?? [];
@endphp
<x-layouts.app :title="$product->name">
    <nav aria-label="Breadcrumb" class="mb-4 text-sm text-stone-600">
        <a class="underline" href="{{ route('products.index') }}">Shoes</a>
        <span aria-hidden="true">/</span>
        <a class="underline" href="{{ route('products.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a>
    </nav>

    <div class="md:grid md:grid-cols-2 md:gap-8">
        <div class="overflow-hidden rounded-xl border border-stone-200">
            <x-product-image :product="$product" />
        </div>

        <div class="mt-5 md:mt-0">
            <h1 class="text-2xl font-bold tracking-tight">{{ $product->name }}</h1>
            <p class="text-stone-600">{{ $product->color }} &middot; {{ ucfirst($product->audience) }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ Money::format($product->price_cents) }}</p>
            <p class="mt-3 text-stone-700">{{ $product->short_description }}</p>

            {{-- On phones the submit button sits in a bar fixed to the bottom of the screen. --}}
            <form method="POST" action="{{ route('cart.items.store') }}" data-cart-form class="mt-5">
                @csrf
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Choose your size (US)</legend>
                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                        @foreach ($product->variants as $variant)
                            @php($band = $variant->stockBand())
                            <label class="relative">
                                <input type="radio" name="variant_id" value="{{ $variant->id }}" class="peer sr-only" @disabled($band === 'none') @checked((int) old('variant_id') === $variant->id)>
                                <span class="flex min-h-11 flex-col items-center justify-center rounded-lg border border-stone-300 bg-white px-1 py-1 text-sm peer-checked:border-emerald-700 peer-checked:bg-emerald-50 peer-checked:ring-2 peer-checked:ring-emerald-700 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-emerald-700 peer-disabled:cursor-not-allowed peer-disabled:bg-stone-100 peer-disabled:text-stone-500 peer-disabled:line-through">
                                    <span class="font-medium">{{ Money::size($variant->size) }}</span>
                                    @if ($band === 'low')
                                        <span class="text-[11px] text-amber-800">Few left</span>
                                    @elseif ($band === 'none')
                                        <span class="text-[11px] no-underline">Sold out</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('variant_id')
                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="mt-4 max-w-32">
                    <label for="quantity" class="mb-1 block text-sm font-medium">Quantity</label>
                    <select id="quantity" name="quantity" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                        @for ($i = 1; $i <= config('store.max_quantity_per_item'); $i++)
                            <option value="{{ $i }}" @selected((int) old('quantity', 1) === $i)>{{ $i }}</option>
                        @endfor
                    </select>
                </div>

                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:static md:z-auto md:mt-5 md:border-0 md:bg-transparent md:p-0">
                    <x-button type="submit" class="w-full md:w-auto md:px-8">Add to cart</x-button>
                </div>
            </form>

            <section class="mt-8 pb-20 md:pb-0" aria-labelledby="details-heading">
                <h2 id="details-heading" class="text-lg font-semibold">Details</h2>
                <p class="mt-2 text-stone-700">{{ $product->description }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-stone-600">Material</dt><dd>{{ $attrs['material'] ?? '—' }}</dd>
                    <dt class="text-stone-600">Weight</dt><dd>{{ isset($attrs['weight_g']) ? $attrs['weight_g'].' g' : '—' }}</dd>
                    <dt class="text-stone-600">Heel-to-toe drop</dt><dd>{{ isset($attrs['drop_mm']) ? $attrs['drop_mm'].' mm' : '—' }}</dd>
                    <dt class="text-stone-600">Waterproof</dt><dd>{{ ($attrs['waterproof'] ?? false) ? 'Yes' : 'No' }}</dd>
                    <dt class="text-stone-600">Width</dt><dd>{{ ucfirst($attrs['width'] ?? 'standard') }}</dd>
                </dl>
            </section>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-10" aria-labelledby="related-heading">
            <h2 id="related-heading" class="text-xl font-semibold">More in {{ $product->category->name }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($related as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
