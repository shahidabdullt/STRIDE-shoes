<?php

namespace App\Actions\Cart;

use App\Exceptions\CartException;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class UpdateCartItem
{
    /** @throws CartException */
    public function __invoke(CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($item, $quantity) {
            $variant = ProductVariant::with('product')->lockForUpdate()->find($item->product_variant_id);

            if ($variant === null || ! $variant->product->is_active || $variant->stock <= 0) {
                throw new CartException('That item is no longer available. Please remove it from your cart.');
            }

            $item->quantity = CartLimits::assertWithinLimit($variant, $quantity);
            $item->save();

            return $item;
        });
    }
}
