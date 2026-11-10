<?php

namespace App\Assistant\Tools;

use App\Actions\Cart\AddItemToCart;
use App\Assistant\ToolContext;
use App\Exceptions\CartException;
use App\Models\ProductVariant;

class AddToCartTool implements AssistantTool
{
    public function __construct(private AddItemToCart $addItem) {}

    public function name(): string
    {
        return 'add_to_cart';
    }

    public function description(): string
    {
        return 'Add a shoe in one US size to the customer\'s own cart. Only do this when the customer asks for it.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string', 'description' => 'Product slug from search_products.'],
                'size' => ['type' => 'number', 'description' => 'US size, 6 to 13 in half sizes.'],
                'quantity' => ['type' => 'integer', 'description' => '1 to 3. Default 1.'],
            ],
            'required' => ['slug', 'size'],
        ];
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
            'size' => ['required', 'numeric', 'between:3,15', 'multiple_of:0.5'],
            'quantity' => ['nullable', 'integer', 'between:1,3'],
        ];
    }

    public function handle(array $args, ToolContext $context): array
    {
        $variant = ProductVariant::query()
            ->where('size', $args['size'])
            ->whereHas('product', fn ($q) => $q->active()->where('slug', $args['slug']))
            ->first();

        if ($variant === null) {
            return ['error' => 'That shoe does not come in that size. Check the sizes with get_product.'];
        }

        // Same action as the shop's "Add to cart" button: same stock and quantity limits.
        try {
            ($this->addItem)($context->cart(create: true), $variant->id, (int) ($args['quantity'] ?? 1), $context->via());
        } catch (CartException $e) {
            return ['error' => $e->getMessage()];
        }

        return ['added' => true, 'cart' => Presenter::cart($context->cart())];
    }
}
