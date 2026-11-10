<?php

namespace App\Actions\Checkout;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\Pricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlaceOrder
{
    /**
     * Turns an open cart into an order. Prices and totals come from the database; stock rows
     * are locked in id order so concurrent checkouts for the last unit cannot both succeed.
     *
     * @param  array{customer_name: string, email: string, address: array<string, string>}  $customer
     *
     * @throws CartException
     */
    public function __invoke(Cart $cart, array $customer): Order
    {
        return DB::transaction(function () use ($cart, $customer) {
            $locked = Cart::lockForUpdate()->find($cart->id);

            if ($locked === null || $locked->status !== 'open') {
                throw new CartException('This cart has already been checked out.');
            }

            $items = $locked->items()->orderBy('product_variant_id')->get();

            if ($items->isEmpty()) {
                throw new CartException('Your cart is empty.');
            }

            $variants = ProductVariant::with('product')
                ->whereIn('id', $items->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $subtotal = 0;

            foreach ($items as $item) {
                $variant = $variants->get($item->product_variant_id);

                if ($variant === null || ! $variant->product->is_active) {
                    throw new CartException('An item in your cart is no longer available. Please review your cart.');
                }

                if ($variant->stock < $item->quantity) {
                    throw new CartException('Sorry, '.$variant->product->name.' in size '.Money::size($variant->size).' is no longer available in the quantity you chose. Please review your cart.');
                }

                $unit = $variant->product->price_cents;
                $subtotal += $unit * $item->quantity;
                $lines[] = [$variant, $item->quantity, $unit];
            }

            $totals = Pricing::totals($subtotal);

            // The unique number is derived from the row id, so insert with a throwaway value first.
            $order = Order::create([
                'number' => 'PENDING-'.Str::uuid(),
                'cart_id' => $locked->id,
                // Attribution: the visitor's most recent assistant conversation, if any (text or voice).
                'conversation_id' => Conversation::where('visitor_id', $locked->visitor_id)->latest('id')->value('id'),
                'customer_name' => $customer['customer_name'],
                'email' => $customer['email'],
                'address' => $customer['address'],
                ...$totals,
                'currency' => config('store.currency'),
                'status' => 'placed',
                'payment_status' => 'simulated',
                'placed_at' => now(),
            ]);
            $order->number = sprintf('STR-%s-%06d', now()->year, $order->id);
            $order->save();

            foreach ($lines as [$variant, $quantity, $unit]) {
                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'size' => $variant->size,
                    'sku' => $variant->sku,
                    'unit_price_cents' => $unit,
                    'quantity' => $quantity,
                    'line_total_cents' => $unit * $quantity,
                ]);

                $variant->decrement('stock', $quantity);
            }

            $locked->update(['status' => 'converted']);

            return $order;
        });
    }
}
