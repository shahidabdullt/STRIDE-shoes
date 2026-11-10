<?php

namespace App\Assistant\Tools;

use App\Assistant\ToolContext;
use App\Models\Product;
use App\Models\ProductVariant;

class GetProductTool implements AssistantTool
{
    public function name(): string
    {
        return 'get_product';
    }

    public function description(): string
    {
        return 'Full details of one shoe by its slug: description, materials, weight, drop, waterproofing, width, price and stock per US size (none, low or available).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['slug' => ['type' => 'string', 'description' => 'Product slug from search_products.']],
            'required' => ['slug'],
        ];
    }

    public function rules(): array
    {
        return ['slug' => ['required', 'string', 'max:255']];
    }

    public function handle(array $args, ToolContext $context): array
    {
        $product = Product::active()->with(['variants', 'category'])->where('slug', $args['slug'])->first();

        if ($product === null) {
            return ['error' => 'No product with that slug. Use search_products to find the right one.'];
        }

        return [
            ...Presenter::productSummary($product),
            'category' => $product->category->name,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'attributes' => $product->attributes,
            'sizes' => $product->variants->map(fn (ProductVariant $v) => [
                'size' => (float) $v->size,
                'stock' => $v->stockBand(),
            ])->values()->all(),
        ];
    }
}
