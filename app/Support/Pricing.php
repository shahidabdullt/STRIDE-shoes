<?php

namespace App\Support;

class Pricing
{
    /** Flat shipping, free once the subtotal reaches the threshold; nothing to ship for an empty cart. */
    public static function shippingCents(int $subtotalCents): int
    {
        if ($subtotalCents <= 0 || $subtotalCents >= config('store.free_shipping_threshold_cents')) {
            return 0;
        }

        return config('store.shipping_flat_cents');
    }

    /** @return array{subtotal_cents: int, shipping_cents: int, total_cents: int} */
    public static function totals(int $subtotalCents): array
    {
        $shipping = self::shippingCents($subtotalCents);

        return [
            'subtotal_cents' => $subtotalCents,
            'shipping_cents' => $shipping,
            'total_cents' => $subtotalCents + $shipping,
        ];
    }
}
