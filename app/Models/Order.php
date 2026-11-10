<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'cart_id', 'conversation_id', 'customer_name', 'email', 'address',
    'subtotal_cents', 'shipping_cents', 'total_cents', 'currency', 'status', 'payment_status', 'placed_at',
])]
class Order extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'address' => 'array',
            'subtotal_cents' => 'integer',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }
}
