@php use App\Support\Money; @endphp
<x-layouts.app title="Your cart">
    <h1 class="text-2xl font-bold tracking-tight">Your cart</h1>

    @if ($items->isEmpty())
        <x-card class="mt-4">
            <p class="font-medium">Your cart is empty.</p>
            <x-button :href="route('products.index')" class="mt-3">Browse shoes</x-button>
        </x-card>
    @else
        <div class="mt-4 lg:grid lg:grid-cols-[1fr_20rem] lg:gap-8">
            <ul class="space-y-3">
                @foreach ($items as $item)
                    @php($product = $item->variant->product)
                    <li class="flex gap-3 rounded-xl border border-stone-200 bg-white p-3">
                        <x-product-image :product="$product" compact />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('products.show', $product) }}" class="font-semibold hover:underline">{{ $product->name }}</a>
                            <p class="text-sm text-stone-600">{{ $product->color }} &middot; Size {{ Money::size($item->variant->size) }}</p>
                            <p class="mt-1 font-medium">{{ Money::format($item->lineTotalCents()) }}</p>

                            <div class="mt-2 flex flex-wrap items-end gap-3">
                                <form method="POST" action="{{ route('cart.items.update', $item) }}" class="flex items-end gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label for="qty-{{ $item->id }}" class="mb-1 block text-xs text-stone-600">Quantity</label>
                                        <select id="qty-{{ $item->id }}" name="quantity" class="min-h-11 rounded-lg border border-stone-300 bg-white px-2 text-base">
                                            @for ($i = 1; $i <= config('store.max_quantity_per_item'); $i++)
                                                <option value="{{ $i }}" @selected($item->quantity === $i)>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <x-button type="submit" variant="secondary">Update</x-button>
                                </form>
                                <form method="POST" action="{{ route('cart.items.destroy', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="min-h-11 rounded-lg px-3 text-sm font-medium text-red-800 hover:bg-red-50">Remove</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <x-card class="mt-6 h-fit lg:mt-0">
                <h2 class="text-lg font-semibold">Order summary</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($totals['subtotal_cents']) }}</dd></div>
                    <div class="flex justify-between"><dt>Shipping</dt><dd>{{ $totals['shipping_cents'] === 0 ? 'Free' : Money::format($totals['shipping_cents']) }}</dd></div>
                    <div class="flex justify-between border-t border-stone-200 pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ Money::format($totals['total_cents']) }}</dd></div>
                </dl>
                @if ($totals['shipping_cents'] > 0)
                    <p class="mt-2 text-xs text-stone-600">Free shipping on orders of {{ Money::format(config('store.free_shipping_threshold_cents')) }} or more.</p>
                @endif
                <x-button :href="route('checkout.create')" class="mt-4 w-full">Checkout</x-button>
            </x-card>
        </div>
    @endif
</x-layouts.app>
