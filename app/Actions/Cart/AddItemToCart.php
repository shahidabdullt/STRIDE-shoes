<?php

namespace App\Actions\Cart;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class AddItemToCart
{
    /** @throws CartException */
    public function __invoke(Cart $cart, int $variantId, int $quantity, string $via = 'ui'): CartItem
    {
        return DB::transaction(function () use ($cart, $variantId, $quantity, $via) {
            $variant = ProductVariant::with('product')->lockForUpdate()->find($variantId);

            if ($variant === null || ! $variant->product->is_active) {
                throw new CartException('That item is no longer available.');
            }

            if ($variant->stock <= 0) {
                throw new CartException('Sorry, '.$variant->product->name.' in size '.Money::size($variant->size).' is sold out.');
            }

            $item = $cart->items()->where('product_variant_id', $variant->id)->lockForUpdate()->first()
                ?? new CartItem(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 0, 'added_via' => $via]);

            $item->quantity = CartLimits::assertWithinLimit($variant, $item->quantity + $quantity);
            $item->save();

            return $item;
        });
    }
}
