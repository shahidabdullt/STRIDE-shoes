@php use App\Support\Money; @endphp
<x-layouts.app title="Checkout">
    <h1 class="text-2xl font-bold tracking-tight">Checkout</h1>

    <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="note">
        <strong>Payment simulated.</strong> This is a demo store: no payment is taken and nothing is charged or shipped. Please don't enter real card details.
    </div>

    <div class="mt-6 lg:grid lg:grid-cols-[1fr_20rem] lg:gap-8">
        <form method="POST" action="{{ route('checkout.store') }}" class="space-y-4" novalidate>
            @csrf
            <x-input name="customer_name" label="Full name" autocomplete="name" required />
            <x-input name="email" label="Email" type="email" inputmode="email" autocomplete="email" required />
            <x-input name="address_line1" label="Address" autocomplete="address-line1" required />
            <x-input name="address_line2" label="Apartment, suite, etc. (optional)" autocomplete="address-line2" />
            <div class="grid grid-cols-6 gap-3">
                <x-input name="city" label="City" autocomplete="address-level2" required class="" />
                <x-input name="state" label="State" autocomplete="address-level1" maxlength="2" placeholder="CA" required />
                <x-input name="postal_code" label="ZIP code" inputmode="numeric" autocomplete="postal-code" required />
            </div>
            <p class="text-sm text-stone-600">We deliver within the United States only.</p>
            <x-button type="submit" class="w-full sm:w-auto sm:px-8">Place order (no charge)</x-button>
        </form>

        <x-card class="mt-6 h-fit lg:mt-0">
            <h2 class="text-lg font-semibold">Your order</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($items as $item)
                    <li class="flex justify-between gap-3">
                        <span>{{ $item->variant->product->name }} &middot; {{ Money::size($item->variant->size) }} &times; {{ $item->quantity }}</span>
                        <span>{{ Money::format($item->lineTotalCents()) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="mt-3 space-y-1 border-t border-stone-200 pt-3 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($totals['subtotal_cents']) }}</dd></div>
                <div class="flex justify-between"><dt>Shipping</dt><dd>{{ $totals['shipping_cents'] === 0 ? 'Free' : Money::format($totals['shipping_cents']) }}</dd></div>
                <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{{ Money::format($totals['total_cents']) }}</dd></div>
            </dl>
        </x-card>
    </div>
</x-layouts.app>
