<?php

namespace Tests\Feature\Shop;

use App\Actions\Catalog\SearchProducts;
use App\Models\Product;
use Tests\Concerns\TruncatesForFulltext;
use Tests\TestCase;

/** Uses TruncatesForFulltext because InnoDB FULLTEXT cannot see rows inside an open transaction. */
class SearchProductsTest extends TestCase
{
    use TruncatesForFulltext;

    private function names(array $filters): array
    {
        return (new SearchProducts)($filters)->orderBy('id')->pluck('name')->all();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Product::factory()->create(['name' => 'Summit Waterproof Trail', 'short_description' => 'Dry feet on wet days', 'color' => 'Charcoal']);
        Product::factory()->create(['name' => 'Canvas Daily', 'short_description' => 'Lightweight canvas', 'color' => 'Red']);
        Product::factory()->create(['name' => 'Pace One', 'short_description' => 'Everyday road trainer', 'color' => 'Black', 'is_active' => false]);
    }

    public function test_it_finds_products_by_prefix_and_ignores_inactive_ones(): void
    {
        $this->assertSame(['Summit Waterproof Trail'], $this->names(['q' => 'waterproof']));
        $this->assertSame(['Summit Waterproof Trail'], $this->names(['q' => 'water']));
        $this->assertSame([], $this->names(['q' => 'pace']));
    }

    public function test_all_words_must_match(): void
    {
        $this->assertSame(['Summit Waterproof Trail'], $this->names(['q' => 'trail waterproof']));
        $this->assertSame([], $this->names(['q' => 'trail canvas']));
    }

    public function test_short_words_fall_back_to_like(): void
    {
        $this->assertSame(['Canvas Daily'], $this->names(['q' => 're']));
    }

    public function test_stopwords_and_operators_are_ignored(): void
    {
        $this->assertSame(['Summit Waterproof Trail'], $this->names(['q' => 'the waterproof for']));
        $this->assertSame(['Summit Waterproof Trail'], $this->names(['q' => '+waterproof* -"" () <> ~']));
        $this->assertCount(2, $this->names(['q' => 'the']));
    }
}
