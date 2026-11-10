<?php

namespace Tests\Feature\Assistant;

use App\Actions\Catalog\SearchProducts;
use App\Assistant\ToolContext;
use App\Assistant\ToolRegistry;
use App\Models\Cart;
use App\Models\Conversation;
use App\Models\ConversationQuestion;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Tests\Concerns\MakesShopData;
use Tests\Concerns\TruncatesForFulltext;
use Tests\TestCase;

/**
 * Each tool through the registry, as the model would call it. TruncatesForFulltext (not transactions)
 * because InnoDB FULLTEXT cannot see uncommitted rows (docs/phase-0-findings.md).
 */
class AssistantToolsTest extends TestCase
{
    use MakesShopData, TruncatesForFulltext;

    private ToolRegistry $registry;

    private ToolContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ToolRegistry::class);
        $this->context = new ToolContext(Conversation::factory()->create());
    }

    private function callTool(string $tool, mixed $args): array
    {
        return $this->registry->execute($tool, $args, $this->context);
    }

    public function test_the_registry_exposes_exactly_the_eight_planned_tools(): void
    {
        $this->assertSame(
            ['search_products', 'get_product', 'check_size_availability', 'search_knowledge', 'view_cart', 'add_to_cart', 'remove_from_cart', 'report_knowledge_gap'],
            $this->registry->names(),
        );

        foreach ($this->registry->interactionsDeclarations() as $declaration) {
            $this->assertSame('function', $declaration['type']);
            $this->assertSame('object', $declaration['parameters']['type']);
            $this->assertNotEmpty($declaration['description']);
        }
    }

    public function test_search_products_filters_clamps_and_never_shows_exact_stock(): void
    {
        $this->makeVariant(stock: 7, size: 9, priceDollars: 120, product: ['name' => 'Ridge Trail', 'activity' => 'trail']);
        $this->makeVariant(stock: 0, size: 9, priceDollars: 90, product: ['name' => 'Sold Out Trail', 'activity' => 'trail']);
        $this->makeVariant(stock: 4, size: 9, priceDollars: 80, product: ['name' => 'Hidden Trail', 'activity' => 'trail', 'is_active' => false]);
        $this->makeVariant(stock: 4, size: 9, priceDollars: 70, product: ['name' => 'City Walker', 'activity' => 'walking']);

        $out = $this->callTool('search_products', ['activity' => 'trail', 'size' => 9, 'limit' => 500]);

        $this->assertSame('ok', $out['status']);
        $this->assertSame(['Ridge Trail'], array_column($out['result']['products'], 'name'));
        $this->assertSame('$120.00', $out['result']['products'][0]['price']);
        $this->assertSame([9.0], $out['result']['products'][0]['sizes_in_stock']);
        $this->assertStringNotContainsString('"stock"', json_encode($out['result']));

        $this->assertSame('invalid_arguments', $this->callTool('search_products', ['size' => 9.25])['error_code']);
        $this->assertSame('invalid_arguments', $this->callTool('search_products', ['activity' => 'skiing'])['error_code']);
    }

    public function test_search_products_handles_hostile_text_and_unknown_keys(): void
    {
        $this->makeVariant(product: ['name' => 'Summit Waterproof Trail']);

        $out = $this->callTool('search_products', ['query' => '+waterproof -"trail* (<>~@', 'sql' => 'DROP TABLE products']);

        $this->assertSame('ok', $out['status']);
        $this->assertSame(['query' => '+waterproof -"trail* (<>~@'], $out['args'], 'unknown keys are dropped');
        $this->assertSame(1, $out['result']['count']);
    }

    public function test_get_product_returns_stock_bands_and_hides_inactive_products(): void
    {
        $variant = $this->makeVariant(stock: 2, size: 8);
        ProductVariant::factory()->create(['product_id' => $variant->product_id, 'size' => 9, 'stock' => 0]);
        ProductVariant::factory()->create(['product_id' => $variant->product_id, 'size' => 10, 'stock' => 20]);

        $out = $this->callTool('get_product', ['slug' => $variant->product->slug])['result'];

        $this->assertSame([['size' => 8.0, 'stock' => 'low'], ['size' => 9.0, 'stock' => 'none'], ['size' => 10.0, 'stock' => 'available']], $out['sizes']);

        $variant->product->update(['is_active' => false]);
        $this->assertArrayHasKey('error', $this->callTool('get_product', ['slug' => $variant->product->slug])['result']);
    }

    public function test_check_size_availability_offers_nearby_sizes(): void
    {
        $variant = $this->makeVariant(stock: 0, size: 9);
        foreach ([[8, 3], [9.5, 10], [10, 1], [12, 5]] as [$size, $stock]) {
            ProductVariant::factory()->create(['product_id' => $variant->product_id, 'size' => $size, 'stock' => $stock]);
        }

        $out = $this->callTool('check_size_availability', ['slug' => $variant->product->slug, 'size' => 9])['result'];

        $this->assertFalse($out['in_stock']);
        $this->assertSame('none', $out['quantity_band']);
        $this->assertSame([8.0, 9.5, 10.0], $out['alternatives']);

        $missing = $this->callTool('check_size_availability', ['slug' => $variant->product->slug, 'size' => 14])['result'];
        $this->assertFalse($missing['size_exists']);
    }

    public function test_search_knowledge_finds_published_faqs_and_articles_only(): void
    {
        Faq::factory()->create(['question' => 'How long do returns take?', 'answer' => 'You can return unworn shoes within 30 days.']);
        Faq::factory()->create(['question' => 'Secret returns policy draft', 'answer' => 'Not public.', 'is_published' => false]);
        KnowledgeArticle::factory()->create(['title' => 'Returns and exchanges', 'body' => 'Returns are free within 30 days of delivery.']);

        $out = $this->callTool('search_knowledge', ['query' => 'what is your return policy?'])['result']['results'];

        $this->assertCount(2, $out);
        $this->assertEqualsCanonicalizing(['faq', 'article'], array_column($out, 'source'));
        $this->assertStringNotContainsString('Not public', json_encode($out));

        $this->assertSame([], $this->callTool('search_knowledge', ['query' => 'iceland'])['result']['results']);
        $this->assertSame([], $this->callTool('search_knowledge', ['query' => '+-*'])['result']['results']);
    }

    public function test_cart_tools_only_touch_the_conversation_visitors_cart(): void
    {
        $variant = $this->makeVariant(stock: 5, size: 9);
        $slug = $variant->product->slug;
        $other = Cart::create(['visitor_id' => fake()->uuid(), 'status' => 'open']);
        $other->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $added = $this->callTool('add_to_cart', ['slug' => $slug, 'size' => 9, 'quantity' => 2]);
        $this->assertTrue($added['result']['added']);
        $this->assertSame(2, $added['result']['cart']['item_count']);

        $this->assertSame(2, $this->callTool('view_cart', [])['result']['item_count']);

        $this->assertTrue($this->callTool('remove_from_cart', ['slug' => $slug, 'size' => 9])['result']['removed']);
        $this->assertSame(0, $this->callTool('view_cart', [])['result']['item_count']);
        $this->assertSame(1, $other->items()->count(), "another visitor's cart is untouched");
    }

    public function test_add_to_cart_limits(): void
    {
        $soldOut = $this->makeVariant(stock: 0, size: 9);
        $one = $this->makeVariant(stock: 1, size: 9);

        $this->assertSame('invalid_arguments', $this->callTool('add_to_cart', ['slug' => $one->product->slug, 'size' => 9, 'quantity' => 4])['error_code']);
        $this->assertStringContainsString('sold out', $this->callTool('add_to_cart', ['slug' => $soldOut->product->slug, 'size' => 9])['result']['error']);
        $this->assertArrayHasKey('error', $this->callTool('add_to_cart', ['slug' => $one->product->slug, 'size' => 9, 'quantity' => 2])['result']);
        $this->assertArrayHasKey('error', $this->callTool('add_to_cart', ['slug' => $one->product->slug, 'size' => 11])['result']);
        $this->assertSame(0, Cart::where('visitor_id', $this->context->visitorId())->first()?->totalQuantity() ?? 0);
    }

    public function test_report_knowledge_gap_is_capped_per_conversation(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->callTool('report_knowledge_gap', ['question' => "Question {$i}?", 'topic' => 'Gift cards']);
        }

        $this->assertSame(10, ConversationQuestion::count());
        $this->assertSame('gift cards', ConversationQuestion::first()->topic);
        $this->assertSame('invalid_arguments', $this->callTool('report_knowledge_gap', ['question' => str_repeat('a', 501), 'topic' => 'x'])['error_code']);
    }

    public function test_a_failing_handler_returns_an_error_result_instead_of_crashing(): void
    {
        Product::query()->delete();
        $this->app->instance(SearchProducts::class, new class extends SearchProducts
        {
            public function __invoke(array $filters): Builder
            {
                throw new \RuntimeException('db down');
            }
        });
        $registry = new ToolRegistry;

        $out = $registry->execute('search_products', [], $this->context);

        $this->assertSame('tool_failed', $out['error_code']);
    }
}
