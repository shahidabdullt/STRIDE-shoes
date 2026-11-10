<?php

namespace App\Assistant\Tools;

use App\Actions\Catalog\SearchProducts;
use App\Assistant\ToolContext;
use App\Models\Product;

class SearchProductsTool implements AssistantTool
{
    private const ACTIVITIES = ['running', 'trail', 'walking', 'casual', 'training'];

    private const AUDIENCES = ['men', 'women', 'unisex'];

    public function __construct(private SearchProducts $search) {}

    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Search the Stride catalog. Returns matching shoes with price, colour and the US sizes currently in stock. Use it before naming any product or price.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Keywords, e.g. "waterproof trail" or "white sneaker".'],
                'activity' => ['type' => 'string', 'enum' => self::ACTIVITIES],
                'audience' => ['type' => 'string', 'enum' => self::AUDIENCES, 'description' => 'men, women or unisex.'],
                'size' => ['type' => 'number', 'description' => 'US size, 6 to 13 in half sizes. Only shoes with this size in stock are returned.'],
                'max_price' => ['type' => 'integer', 'description' => 'Highest price in whole dollars.'],
                'limit' => ['type' => 'integer', 'description' => 'How many results, 1 to 8. Default 5.'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100'],
            'activity' => ['nullable', 'in:'.implode(',', self::ACTIVITIES)],
            'audience' => ['nullable', 'in:'.implode(',', self::AUDIENCES)],
            'size' => ['nullable', 'numeric', 'between:3,15', 'multiple_of:0.5'],
            'max_price' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limit' => ['nullable', 'integer'],
        ];
    }

    public function handle(array $args, ToolContext $context): array
    {
        // Clamped rather than rejected: a model asking for 50 results should still get an answer.
        $limit = max(1, min(8, (int) ($args['limit'] ?? 5)));

        $products = ($this->search)([
            'q' => $args['query'] ?? '',
            'activity' => $args['activity'] ?? null,
            'audience' => $args['audience'] ?? null,
            'size' => $args['size'] ?? null,
            'max_price' => $args['max_price'] ?? null,
        ])->with('variants')->limit($limit)->get();

        return [
            'count' => $products->count(),
            'products' => $products->map(fn (Product $p) => Presenter::productSummary($p))->values()->all(),
        ];
    }
}
