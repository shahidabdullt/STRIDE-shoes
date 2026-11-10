<?php

namespace App\Assistant\Tools;

use App\Actions\Cart\RemoveCartItem;
use App\Assistant\ToolContext;

class RemoveFromCartTool implements AssistantTool
{
    public function __construct(private RemoveCartItem $removeItem) {}

    public function name(): string
    {
        return 'remove_from_cart';
    }

    public function description(): string
    {
        return 'Remove a shoe in one US size from the customer\'s own cart.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string', 'description' => 'Product slug, as shown by view_cart.'],
                'size' => ['type' => 'number', 'description' => 'US size of the item to remove.'],
            ],
            'required' => ['slug', 'size'],
        ];
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
            'size' => ['required', 'numeric', 'between:3,15', 'multiple_of:0.5'],
        ];
    }

    public function handle(array $args, ToolContext $context): array
    {
        $cart = $context->cart();

        // Looked up inside this visitor's cart only, so another visitor's items can never be touched.
        $item = $cart?->items()
            ->whereHas('variant', fn ($q) => $q->where('size', $args['size'])
                ->whereHas('product', fn ($p) => $p->where('slug', $args['slug'])))
            ->first();

        if ($item === null) {
            return ['error' => 'That item is not in the cart. Use view_cart to see what is there.'];
        }

        ($this->removeItem)($item);

        return ['removed' => true, 'cart' => Presenter::cart($cart)];
    }
}
