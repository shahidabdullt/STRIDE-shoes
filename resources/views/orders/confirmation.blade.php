@php use App\Support\Money; @endphp
<x-layouts.app title="Order confirmed">
    <div class="max-w-2xl">
        <x-badge variant="success">Order placed</x-badge>
        <h1 class="mt-3 text-2xl font-bold tracking-tight">Thank you, {{ $order->customer_name }}!</h1>
        <p class="mt-1 text-stone-700">Your order number is <strong>{{ $order->number }}</strong>. This was a demo order: payment was simulated and nothing was charged.</p>

        <x-card class="mt-6">
            <h2 class="text-lg font-semibold">Items</h2>
            <ul class="mt-3 divide-y divide-stone-200 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 py-2">
                        <span>{{ $item->product_name }} &middot; Size {{ Money::size($item->size) }} &times; {{ $item->quantity }}</span>
                        <span>{{ Money::format($item->line_total_cents) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="mt-3 space-y-1 border-t border-stone-200 pt-3 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($order->subtotal_cents) }}</dd></div>
                <div class="flex justify-between"><dt>Shipping</dt><dd>{{ $order->shipping_cents === 0 ? 'Free' : Money::format($order->shipping_cents) }}</dd></div>
                <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{{ Money::format($order->total_cents) }}</dd></div>
            </dl>
        </x-card>

        <x-card class="mt-4">
            <h2 class="text-lg font-semibold">Delivery address</h2>
            <address class="mt-2 text-sm not-italic text-stone-700">
                {{ $order->customer_name }}<br>
                {{ $order->address['line1'] }}<br>
                @if (! empty($order->address['line2'])){{ $order->address['line2'] }}<br>@endif
                {{ $order->address['city'] }}, {{ $order->address['state'] }} {{ $order->address['postal_code'] }}
            </address>
        </x-card>

        <x-button :href="route('products.index')" class="mt-6">Continue shopping</x-button>
    </div>
</x-layouts.app>
