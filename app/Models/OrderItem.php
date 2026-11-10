<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_variant_id', 'product_name', 'size', 'sku', 'unit_price_cents', 'quantity', 'line_total_cents'])]
class OrderItem extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'decimal:1',
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
