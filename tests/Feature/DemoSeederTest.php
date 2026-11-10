<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_data_has_the_planned_shape(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, Category::count());
        $this->assertSame(40, Product::count());
        $this->assertSame(25, Faq::count());
        $this->assertSame(6, KnowledgeArticle::count());

        $this->assertSame(5900, (int) Product::min('price_cents'));
        $this->assertSame(18900, (int) Product::max('price_cents'));
        $this->assertSame(0, Product::doesntHave('variants')->count());
        $this->assertSame(0, Category::doesntHave('products')->count());
    }

    public function test_sizes_are_half_sizes_between_6_and_13_and_some_are_sold_out(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6.0, (float) ProductVariant::min('size'));
        $this->assertSame(13.0, (float) ProductVariant::max('size'));
        $this->assertSame(0, ProductVariant::whereRaw('MOD(size * 10, 5) <> 0')->count());
        $this->assertGreaterThan(0, ProductVariant::where('stock', 0)->count());
        $this->assertGreaterThan(0, ProductVariant::where('stock', '>', 0)->count());
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DatabaseSeeder::class);
        $variants = ProductVariant::count();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(40, Product::count());
        $this->assertSame($variants, ProductVariant::count());
        $this->assertSame(25, Faq::count());
        $this->assertSame(6, KnowledgeArticle::count());
    }

    public function test_deliberate_knowledge_gaps_stay_uncovered(): void
    {
        $this->seed(DatabaseSeeder::class);

        $text = strtolower(Faq::pluck('answer')->join(' ').' '.KnowledgeArticle::pluck('body')->join(' '));

        $this->assertStringNotContainsString('international', $text);
        $this->assertStringNotContainsString('gift card', $text);
    }

    public function test_always_include_knowledge_is_small(): void
    {
        $this->seed(DatabaseSeeder::class);

        $words = KnowledgeArticle::where('always_include', true)->pluck('body')->sum(fn ($b) => str_word_count($b));

        $this->assertGreaterThan(0, $words);
        $this->assertLessThan(300, $words);
    }

    public function test_a_known_admin_is_only_created_locally(): void
    {
        $this->app['env'] = 'testing';
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, User::count());

        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);
        $this->assertTrue(User::where('email', 'admin@example.com')->firstOrFail()->is_admin);
    }
}
