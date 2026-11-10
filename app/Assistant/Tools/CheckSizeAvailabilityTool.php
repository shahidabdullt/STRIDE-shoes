<?php

namespace App\Assistant\Tools;

use App\Assistant\ToolContext;
use App\Models\Product;
use App\Models\ProductVariant;

class CheckSizeAvailabilityTool implements AssistantTool
{
    public function name(): string
    {
        return 'check_size_availability';
    }

    public function description(): string
    {
        return 'Check whether a shoe is in stock in one US size. Returns in_stock, a quantity band (none, low, available) and nearby sizes that are in stock.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string', 'description' => 'Product slug from search_products.'],
                'size' => ['type' => 'number', 'description' => 'US size, 6 to 13 in half sizes.'],
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
        $product = Product::active()->with('variants')->where('slug', $args['slug'])->first();

        if ($product === null) {
            return ['error' => 'No product with that slug. Use search_products to find the right one.'];
        }

        $size = (float) $args['size'];
        $variant = $product->variants->first(fn (ProductVariant $v) => (float) $v->size === $size);
        $band = $variant?->stockBand() ?? 'none';

        // The closest in-stock sizes, nearest first.
        $alternatives = collect(Presenter::sizesInStock($product))
            ->reject(fn (float $s) => $s === $size)
            ->sortBy(fn (float $s) => abs($s - $size))
            ->take(3)->sort()->values()->all();

        return [
            'product' => $product->name,
            'slug' => $product->slug,
            'size' => $size,
            'size_exists' => $variant !== null,
            'in_stock' => $band !== 'none',
            'quantity_band' => $band,
            'alternatives' => $alternatives,
        ];
    }
}
