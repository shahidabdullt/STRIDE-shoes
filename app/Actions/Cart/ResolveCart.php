<?php

namespace App\Actions\Cart;

use App\Models\Cart;

class ResolveCart
{
    /** The visitor's open cart, created on demand only when asked to. */
    public function __invoke(string $visitorId, bool $create = false): ?Cart
    {
        $cart = Cart::open()->where('visitor_id', $visitorId)->latest('id')->first();

        if ($cart === null && $create) {
            $cart = Cart::create(['visitor_id' => $visitorId, 'status' => 'open']);
        }

        return $cart;
    }
}
