<?php

namespace App\Assistant\Tools;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;

/** Shapes database rows into the small, exact-stock-free payloads the model sees. */
class Presenter
{
    /** @return array<string, mixed> */
    public static function productSummary(Product $product): array
    {
        return [
            'slug' => $product->slug,
            'name' => $product->name,
            'price' => Money::format($product->price_cents),
            'activity' => $product->activity,
            'audience' => $product->audience,
            'color' => $product->color,
            'sizes_in_stock' => self::sizesInStock($product),
            'url' => route('products.show', $product->slug),
        ];
    }

    /** @return list<float> */
    public static function sizesInStock(Product $product): array
    {
        return $product->variants->where('stock', '>', 0)->map(fn (ProductVariant $v) => (float) $v->size)->values()->all();
    }

    /** @return array<string, mixed> */
    public static function cart(?Cart $cart): array
    {
        if ($cart === null) {
            return ['items' => [], 'item_count' => 0, 'subtotal' => Money::format(0)];
        }

        $items = $cart->items()->with('variant.product')->get();
        $totals = $cart->totals();

        return [
            'items' => $items->map(fn ($item) => [
                'slug' => $item->variant->product->slug,
                'name' => $item->variant->product->name,
                'size' => (float) $item->variant->size,
                'quantity' => $item->quantity,
                'unit_price' => Money::format($item->variant->product->price_cents),
                'line_total' => Money::format($item->variant->product->price_cents * $item->quantity),
            ])->values()->all(),
            'item_count' => (int) $items->sum('quantity'),
            'subtotal' => Money::format($totals['subtotal_cents']),
            'shipping' => $totals['shipping_cents'] === 0 ? 'Free' : Money::format($totals['shipping_cents']),
            'total' => Money::format($totals['total_cents']),
            'checkout_url' => route('checkout.create'),
        ];
    }
}
