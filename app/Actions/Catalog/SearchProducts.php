<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Support\SearchTerms;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single product search used by the shop (and later by the assistant tools).
 *
 * Recognised filters: q, category (slug), activity, audience, size, min_price and max_price
 * (whole currency units), sort. Values are expected to be validated already.
 */
class SearchProducts
{
    /** @param array<string, mixed> $filters
     * @return Builder<Product>
     */
    public function __invoke(array $filters): Builder
    {
        $query = Product::query()->active()->with('category');

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
        }

        foreach (['activity', 'audience'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        // Only products with that size actually in stock.
        if (isset($filters['size']) && $filters['size'] !== '') {
            $query->whereHas('variants', fn (Builder $q) => $q->where('size', $filters['size'])->where('stock', '>', 0));
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price_cents', '>=', (int) $filters['min_price'] * 100);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price_cents', '<=', (int) $filters['max_price'] * 100);
        }

        $this->applyText($query, (string) ($filters['q'] ?? ''));
        $this->applySort($query, $filters['sort'] ?? null);

        return $query;
    }

    /** @param Builder<Product> $query */
    private function applyText(Builder $query, string $text): void
    {
        $words = SearchTerms::words($text);
        $boolean = SearchTerms::booleanQuery($words);

        // FULLTEXT ignores tokens under 3 characters, so those use LIKE instead.
        $short = array_filter($words, fn (string $w) => mb_strlen($w) < 3);

        if ($boolean !== '') {
            $query->whereRaw('MATCH(name, short_description, description) AGAINST (? IN BOOLEAN MODE)', [$boolean]);
        }

        foreach ($short as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('color', 'like', $like));
        }
    }

    /** @param Builder<Product> $query */
    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price_cents')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price_cents')->orderBy('id'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderByDesc('is_featured')->orderBy('id'),
        };
    }
}
