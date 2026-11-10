<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'size', 'sku', 'stock'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'decimal:1',
            'stock' => 'integer',
        ];
    }

    /** Exact stock is never shown to customers or the assistant: only none / low / available. */
    public function stockBand(): string
    {
        return match (true) {
            $this->stock <= 0 => 'none',
            $this->stock <= config('store.low_stock_threshold') => 'low',
            default => 'available',
        };
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
