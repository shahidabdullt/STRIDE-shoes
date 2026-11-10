<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\ResolveCart;
use App\Actions\Checkout\PlaceOrder;
use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Requests\Shop\CheckoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Request $request, ResolveCart $resolveCart): View|RedirectResponse
    {
        $cart = $resolveCart(EnsureVisitorId::idFor($request));

        if ($cart === null || $cart->items()->doesntExist()) {
            return redirect()->route('cart.show')->with('error', 'Your cart is empty.');
        }

        return view('checkout.create', [
            'items' => $cart->items()->with('variant.product')->get(),
            'totals' => $cart->totals(),
        ]);
    }

    public function store(CheckoutRequest $request, ResolveCart $resolveCart, PlaceOrder $placeOrder): RedirectResponse
    {
        $cart = $resolveCart(EnsureVisitorId::idFor($request));

        if ($cart === null) {
            return redirect()->route('cart.show')->with('error', 'Your cart is empty.');
        }

        try {
            $order = $placeOrder($cart, $request->customer());
        } catch (CartException $e) {
            return redirect()->route('cart.show')->with('error', $e->getMessage());
        }

        return redirect(URL::temporarySignedRoute(
            'orders.confirmation',
            now()->addHours(config('store.confirmation_link_hours')),
            ['order' => $order->number],
        ));
    }
}
