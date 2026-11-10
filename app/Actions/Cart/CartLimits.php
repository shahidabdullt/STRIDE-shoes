<?php

namespace App\Actions\Cart;

use App\Exceptions\CartException;
use App\Models\ProductVariant;

class CartLimits
{
    /**
     * Returns the quantity when it is allowed, otherwise explains the limit.
     *
     * @throws CartException
     */
    public static function assertWithinLimit(ProductVariant $variant, int $quantity): int
    {
        $max = min(config('store.max_quantity_per_item'), $variant->stock);

        if ($quantity > $max) {
            throw new CartException($max === $variant->stock && $variant->stock < config('store.max_quantity_per_item')
                ? 'Only '.$max.' of this size '.($max === 1 ? 'is' : 'are').' available.'
                : 'You can order at most '.$max.' of one size.');
        }

        return $quantity;
    }
}
