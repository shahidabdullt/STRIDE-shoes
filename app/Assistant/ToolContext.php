<?php

namespace App\Assistant;

use App\Actions\Cart\ResolveCart;
use App\Models\Cart;
use App\Models\Conversation;

/**
 * Who a tool runs for. Tools only ever touch this visitor's cart and this conversation,
 * whatever the model asks for.
 */
class ToolContext
{
    public function __construct(
        public readonly Conversation $conversation,
    ) {}

    public function visitorId(): string
    {
        return $this->conversation->visitor_id;
    }

    /** `added_via` value for cart items: chat or voice. */
    public function via(): string
    {
        return $this->conversation->channel === 'voice' ? 'voice' : 'chat';
    }

    /** The visitor's open cart; also links it to the conversation so orders can be attributed. */
    public function cart(bool $create = false): ?Cart
    {
        $cart = app(ResolveCart::class)($this->visitorId(), $create);

        if ($cart !== null && $this->conversation->cart_id !== $cart->id) {
            $this->conversation->forceFill(['cart_id' => $cart->id])->save();
        }

        return $cart;
    }
}
