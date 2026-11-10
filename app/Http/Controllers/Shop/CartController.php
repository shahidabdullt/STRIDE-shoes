<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\ResolveCart;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVisitorId;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Request $request, ResolveCart $resolveCart): View
    {
        $cart = $resolveCart(EnsureVisitorId::idFor($request));

        return view('cart.show', [
            'items' => $cart ? $cart->items()->with('variant.product')->get() : collect(),
            'totals' => $cart?->totals(),
        ]);
    }
}
