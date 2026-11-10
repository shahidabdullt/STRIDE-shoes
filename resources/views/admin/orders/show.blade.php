@php use App\Support\Money; @endphp
<x-layouts.app :title="'Order '.$order->number">
    <x-admin.nav />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold tracking-tight">{{ $order->number }}</h1>
        <x-badge variant="success">{{ ucfirst($order->status) }}</x-badge>
        <x-badge>Payment simulated</x-badge>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="text-lg font-semibold">Customer</h2>
            <p class="mt-2 text-sm">{{ $order->customer_name }}<br>{{ $order->email }}</p>
            <address class="mt-3 text-sm not-italic text-stone-700">
                {{ $order->address['line1'] }}<br>
                @if (! empty($order->address['line2'])){{ $order->address['line2'] }}<br>@endif
                {{ $order->address['city'] }}, {{ $order->address['state'] }} {{ $order->address['postal_code'] }}
            </address>
            <p class="mt-3 text-sm text-stone-600">Placed {{ $order->placed_at->format('j M Y, H:i') }}</p>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold">Items</h2>
            <ul class="mt-3 divide-y divide-stone-200 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 py-2">
                        <span class="min-w-0">{{ $item->product_name }} &middot; Size {{ Money::size($item->size) }} &times; {{ $item->quantity }}<br><span class="text-stone-600">{{ $item->sku }}</span></span>
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
    </div>

    <x-button variant="secondary" :href="route('admin.orders.index')" class="mt-4">Back to orders</x-button>
</x-layouts.app>
