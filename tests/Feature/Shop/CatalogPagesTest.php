<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_shows_featured_products_and_categories(): void
    {
        $category = Category::factory()->create(['name' => 'Road Running']);
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Pace One', 'is_featured' => true]);
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Hidden Gem', 'is_featured' => false]);

        $this->get('/')->assertOk()->assertSee('Pace One')->assertSee('Road Running')->assertDontSee('Hidden Gem');
    }

    public function test_the_product_list_shows_only_active_products(): void
    {
        Product::factory()->create(['name' => 'Visible Shoe']);
        Product::factory()->inactive()->create(['name' => 'Retired Shoe']);

        $this->get('/shoes')->assertOk()->assertSee('Visible Shoe')->assertDontSee('Retired Shoe');
    }

    public function test_the_list_has_a_filter_drawer_on_phones(): void
    {
        $this->get('/shoes')
            ->assertSee('data-drawer-open="filters"', false)
            ->assertSee('id="filters"', false)
            ->assertSee('aria-modal="true"', false);
    }

    public function test_filters_narrow_the_results(): void
    {
        $road = Category::factory()->create(['slug' => 'road']);
        Product::factory()->create(['name' => 'Road Cheap', 'category_id' => $road->id, 'activity' => 'running', 'audience' => 'men', 'price_cents' => 8000]);
        Product::factory()->create(['name' => 'Road Pricey', 'category_id' => $road->id, 'activity' => 'running', 'audience' => 'women', 'price_cents' => 15000]);
        Product::factory()->create(['name' => 'Trail Mid', 'activity' => 'trail', 'audience' => 'men', 'price_cents' => 12000]);

        $this->get('/shoes?category=road')->assertSee('Road Cheap')->assertSee('Road Pricey')->assertDontSee('Trail Mid');
        $this->get('/shoes?activity=trail')->assertSee('Trail Mid')->assertDontSee('Road Cheap');
        $this->get('/shoes?audience=women')->assertSee('Road Pricey')->assertDontSee('Road Cheap');
        $this->get('/shoes?min_price=100&max_price=130')->assertSee('Trail Mid')->assertDontSee('Road Cheap')->assertDontSee('Road Pricey');
    }

    public function test_the_size_filter_only_matches_sizes_in_stock(): void
    {
        $inStock = Product::factory()->create(['name' => 'Has Nine']);
        ProductVariant::factory()->create(['product_id' => $inStock->id, 'size' => 9, 'stock' => 3]);
        $soldOut = Product::factory()->create(['name' => 'No Nine']);
        ProductVariant::factory()->create(['product_id' => $soldOut->id, 'size' => 9, 'stock' => 0]);

        $this->get('/shoes?size=9')->assertSee('Has Nine')->assertDontSee('No Nine');
        $this->get('/shoes?size=9.0')->assertSee('Has Nine');
    }

    public function test_sorting_by_price(): void
    {
        Product::factory()->create(['name' => 'Aaa Cheap', 'price_cents' => 6000]);
        Product::factory()->create(['name' => 'Zzz Dear', 'price_cents' => 18000]);

        $this->get('/shoes?sort=price_desc')->assertSeeInOrder(['Zzz Dear', 'Aaa Cheap']);
        $this->get('/shoes?sort=price_asc')->assertSeeInOrder(['Aaa Cheap', 'Zzz Dear']);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_redirect_with_errors_and_never_500(string $query, string $field): void
    {
        $this->get('/shoes?'.$query)->assertRedirect(route('products.index'))->assertSessionHasErrors($field);
    }

    public function test_invalid_filters_return_422_for_json(): void
    {
        $this->getJson('/shoes?activity=flying')->assertStatus(422)->assertJsonValidationErrors('activity');
    }

    public static function invalidFilters(): array
    {
        return [
            'unknown activity' => ['activity=flying', 'activity'],
            'unknown audience' => ['audience=robots', 'audience'],
            'unknown category' => ['category=nope', 'category'],
            'non numeric size' => ['size=abc', 'size'],
            'size out of range' => ['size=99', 'size'],
            'quarter size' => ['size=9.25', 'size'],
            'negative price' => ['min_price=-5', 'min_price'],
            'max below min' => ['min_price=100&max_price=50', 'max_price'],
            'array injection' => ['q[]=a', 'q'],
            'bad sort' => ['sort=drop table', 'sort'],
            'bad page' => ['page=0', 'page'],
            'overlong search' => ['q='.str_repeat('a', 101), 'q'],
        ];
    }

    public function test_hostile_search_text_does_not_break_the_page(): void
    {
        Product::factory()->create();

        foreach (['+++', '"unbalanced', '-(*', '@@@ <> ~', 'a', "o'brien; --"] as $q) {
            $this->get('/shoes?'.http_build_query(['q' => $q]))->assertOk();
        }
    }

    public function test_the_product_page_shows_sizes_stock_bands_and_details(): void
    {
        $product = Product::factory()->create(['name' => 'Summit', 'attributes' => ['material' => 'Ripstop', 'weight_g' => 300, 'drop_mm' => 8, 'waterproof' => true, 'width' => 'wide']]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 9, 'stock' => 10]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 9.5, 'stock' => 2]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 10, 'stock' => 0]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Summit')
            ->assertSee('Few left')
            ->assertSee('Sold out')
            ->assertSee('Ripstop')
            ->assertSee('Add to cart')
            ->assertSee('md:static', false);
    }

    public function test_exact_stock_numbers_are_never_shown(): void
    {
        $variant = $this->variantWithStock(7);

        $this->get(route('products.show', $variant->product))->assertDontSee('7 left')->assertDontSee('7 in stock');
    }

    public function test_an_inactive_product_page_is_a_404(): void
    {
        $this->get(route('products.show', Product::factory()->inactive()->create()))->assertNotFound();
    }

    public function test_the_help_page_lists_published_faqs_and_articles_only(): void
    {
        Faq::factory()->create(['question' => 'Published question?', 'topic' => 'shipping']);
        Faq::factory()->create(['question' => 'Draft question?', 'is_published' => false]);
        KnowledgeArticle::factory()->create(['title' => 'Public guide']);
        KnowledgeArticle::factory()->create(['title' => 'Secret draft', 'is_published' => false]);

        $this->get('/help')->assertOk()->assertSee('Published question?')->assertDontSee('Draft question?')->assertSee('Public guide')->assertDontSee('Secret draft');
    }

    private function variantWithStock(int $stock): ProductVariant
    {
        return ProductVariant::factory()->create(['stock' => $stock])->load('product');
    }
}
