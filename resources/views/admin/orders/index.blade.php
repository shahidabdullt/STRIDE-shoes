@php use App\Support\Money; @endphp
<x-layouts.app title="Orders">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">Orders</h1>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse ($orders as $order)
            <li>
                <a href="{{ route('admin.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-stone-50">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $order->number }}</p>
                        <p class="text-sm text-stone-600">{{ $order->customer_name }} &middot; {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }} &middot; {{ $order->placed_at->format('j M Y, H:i') }}</p>
                    </div>
                    <span class="font-semibold">{{ Money::format($order->total_cents) }}</span>
                </a>
            </li>
        @empty
            <li class="p-4 text-stone-600">No orders yet.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.app>
