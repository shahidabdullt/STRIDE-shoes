<?php

namespace App\Models;

use App\Support\Pricing;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['visitor_id', 'status'])]
class Cart extends Model
{
    /** @param Builder<Cart> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function totalQuantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    /**
     * Prices always come from the database, never from the browser.
     *
     * @return array{subtotal_cents: int, shipping_cents: int, total_cents: int}
     */
    public function totals(): array
    {
        $subtotal = (int) $this->items()
            ->join('product_variants', 'product_variants.id', '=', 'cart_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->sum(DB::raw('products.price_cents * cart_items.quantity'));

        return Pricing::totals($subtotal);
    }
}
