<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Product;
use Tests\Concerns\TruncatesForFulltext;
use Tests\TestCase;

/**
 * InnoDB FULLTEXT indexes only see committed rows, so these tests cannot run inside the
 * transaction that RefreshDatabase wraps around each test. TruncatesForFulltext commits for real
 * and empties the tables afterwards. Any future search test (Phase 2 SearchProducts) must do the same.
 */
class FulltextSearchTest extends TestCase
{
    use TruncatesForFulltext;

    public function test_product_fulltext_search_works_on_mysql(): void
    {
        Product::factory()->create(['name' => 'Summit Waterproof Trail Shoe']);
        Product::factory()->create(['name' => 'Canvas Daily']);

        $hits = Product::whereRaw('MATCH(name, short_description, description) AGAINST (? IN BOOLEAN MODE)', ['+waterproof*'])->get();

        $this->assertCount(1, $hits);
        $this->assertSame('Summit Waterproof Trail Shoe', $hits->first()->name);
    }

    public function test_faq_and_article_fulltext_indexes_work(): void
    {
        Faq::factory()->create(['question' => 'How much does shipping cost?', 'answer' => 'A flat fee applies.']);
        KnowledgeArticle::factory()->create(['title' => 'Size guide', 'body' => 'Measure your foot in the evening.']);

        $this->assertCount(1, Faq::whereRaw('MATCH(question, answer) AGAINST (? IN BOOLEAN MODE)', ['+shipping*'])->get());
        $this->assertCount(1, KnowledgeArticle::whereRaw('MATCH(title, body) AGAINST (? IN BOOLEAN MODE)', ['+measure*'])->get());
    }
}
