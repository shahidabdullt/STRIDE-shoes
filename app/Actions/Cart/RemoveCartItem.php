<?php

namespace App\Actions\Cart;

use App\Models\CartItem;

class RemoveCartItem
{
    public function __invoke(CartItem $item): void
    {
        $item->delete();
    }
}
