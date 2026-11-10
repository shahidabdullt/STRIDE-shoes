<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\RemoveCartItem;
use App\Actions\Cart\ResolveCart;
use App\Actions\Cart\UpdateCartItem;
use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Requests\Shop\AddCartItemRequest;
use App\Http\Requests\Shop\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function store(AddCartItemRequest $request, ResolveCart $resolveCart, AddItemToCart $add): JsonResponse|RedirectResponse
    {
        $cart = $resolveCart(EnsureVisitorId::idFor($request), create: true);

        try {
            $add($cart, (int) $request->validated('variant_id'), (int) $request->validated('quantity'));
        } catch (CartException $e) {
            return $this->failure($request, $e->getMessage());
        }

        return $this->success($request, $cart, 'Added to your cart.');
    }

    public function update(UpdateCartItemRequest $request, CartItem $item, ResolveCart $resolveCart, UpdateCartItem $update): JsonResponse|RedirectResponse
    {
        $cart = $this->ownCart($request, $item, $resolveCart);

        try {
            $update($item, (int) $request->validated('quantity'));
        } catch (CartException $e) {
            return $this->failure($request, $e->getMessage());
        }

        return $this->success($request, $cart, 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $item, ResolveCart $resolveCart, RemoveCartItem $remove): JsonResponse|RedirectResponse
    {
        $cart = $this->ownCart($request, $item, $resolveCart);

        $remove($item);

        return $this->success($request, $cart, 'Item removed.');
    }

    /** An item that belongs to someone else's cart looks exactly like one that does not exist. */
    private function ownCart(Request $request, CartItem $item, ResolveCart $resolveCart): Cart
    {
        $cart = $resolveCart(EnsureVisitorId::idFor($request));

        abort_unless($cart !== null && $item->cart_id === $cart->id, 404);

        return $cart;
    }

    private function success(Request $request, Cart $cart, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'cart' => ['count' => $cart->totalQuantity()]]);
        }

        return back()->with('status', $message);
    }

    private function failure(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message);
    }
}
