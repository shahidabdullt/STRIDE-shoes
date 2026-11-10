<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Trail Blazer',
            'slug' => '',
            'short_description' => 'Grippy trail shoe',
            'description' => 'A tough trail shoe.',
            'price' => '129.99',
            'audience' => 'unisex',
            'activity' => 'trail',
            'color' => 'Green',
            'material' => 'Mesh',
            'weight_g' => '290',
            'drop_mm' => '6',
            'width' => 'standard',
            'waterproof' => '1',
            'is_active' => '1',
            'is_featured' => '0',
            'variants' => [
                ['id' => '', 'size' => '9', 'sku' => 'TB-GRN-9', 'stock' => '4'],
                ['id' => '', 'size' => '9.5', 'sku' => 'TB-GRN-95', 'stock' => '0'],
                ['id' => '', 'size' => '', 'sku' => '', 'stock' => ''],
            ],
            ...$overrides,
        ];
    }

    public function test_a_product_is_created_with_server_converted_price_and_sizes(): void
    {
        $this->post('/admin/products', $this->payload())->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertSame(12999, $product->price_cents);
        $this->assertSame('trail-blazer-green', $product->slug);
        $this->assertTrue($product->attributes['waterproof']);
        $this->assertSame(290, $product->attributes['weight_g']);
        $this->assertSame([9.0, 9.5], $product->variants->map(fn ($v) => (float) $v->size)->all());
        $this->assertSame(2, $product->variants->count(), 'blank rows are ignored');
    }

    public function test_the_product_appears_in_the_shop_only_when_active(): void
    {
        $this->post('/admin/products', $this->payload(['is_active' => '0']));
        $product = Product::firstOrFail();

        $this->get("/shoes/{$product->slug}")->assertNotFound();

        $this->put("/admin/products/{$product->id}", $this->payload([
            'category_id' => $product->category_id, 'slug' => $product->slug, 'is_active' => '1',
            'variants' => $product->variants->map(fn ($v) => ['id' => $v->id, 'size' => $v->size, 'sku' => $v->sku, 'stock' => $v->stock])->all(),
        ]))->assertSessionHasNoErrors();

        $this->get("/shoes/{$product->slug}")->assertOk()->assertSee('Trail Blazer');
    }

    public function test_product_validation(): void
    {
        $this->post('/admin/products', $this->payload(['name' => '', 'price' => '0', 'audience' => 'robots', 'activity' => 'flying', 'category_id' => 9999]))
            ->assertSessionHasErrors(['name', 'price', 'audience', 'activity', 'category_id']);
        $this->post('/admin/products', $this->payload(['price' => '12.345']))->assertSessionHasErrors('price');
        $this->post('/admin/products', $this->payload(['variants' => []]))->assertSessionHasErrors('variants');
        $this->assertSame(0, Product::count());
    }

    public function test_variant_rules(): void
    {
        $cases = [
            'quarter size' => [[['size' => '9.25', 'sku' => 'A', 'stock' => '1']], 'variants.0.size'],
            'out of range' => [[['size' => '40', 'sku' => 'A', 'stock' => '1']], 'variants.0.size'],
            'negative stock' => [[['size' => '9', 'sku' => 'A', 'stock' => '-1']], 'variants.0.stock'],
            'bad sku' => [[['size' => '9', 'sku' => 'a b!', 'stock' => '1']], 'variants.0.sku'],
            'duplicate size' => [[['size' => '9', 'sku' => 'A', 'stock' => '1'], ['size' => '9.0', 'sku' => 'B', 'stock' => '1']], 'variants.1.size'],
            'duplicate sku' => [[['size' => '9', 'sku' => 'A', 'stock' => '1'], ['size' => '10', 'sku' => 'a', 'stock' => '1']], 'variants.1.sku'],
        ];

        foreach ($cases as $name => [$variants, $field]) {
            $this->post('/admin/products', $this->payload(['variants' => $variants]))->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Product::count());
    }

    public function test_a_sku_used_by_another_product_is_rejected(): void
    {
        ProductVariant::factory()->create(['sku' => 'TAKEN-1']);

        $this->post('/admin/products', $this->payload(['variants' => [['id' => '', 'size' => '9', 'sku' => 'TAKEN-1', 'stock' => '1']]]))
            ->assertSessionHasErrors('variants.0.sku');
    }

    public function test_editing_updates_stock_and_adds_a_size_but_never_removes_one(): void
    {
        $this->post('/admin/products', $this->payload());
        $product = Product::firstOrFail();
        [$nine, $nineHalf] = $product->variants->all();

        $this->put("/admin/products/{$product->id}", $this->payload([
            'category_id' => $product->category_id, 'slug' => $product->slug,
            'variants' => [
                ['id' => $nine->id, 'size' => '9', 'sku' => 'TB-GRN-9', 'stock' => '11'],
                ['id' => '', 'size' => '10', 'sku' => 'TB-GRN-10', 'stock' => '2'],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(11, $nine->fresh()->stock);
        $this->assertModelExists($nineHalf);
        $this->assertSame(3, $product->variants()->count());
    }

    public function test_a_variant_of_another_product_cannot_be_edited_through_this_one(): void
    {
        $this->post('/admin/products', $this->payload());
        $product = Product::firstOrFail();
        $foreign = ProductVariant::factory()->create(['stock' => 7]);

        $this->put("/admin/products/{$product->id}", $this->payload([
            'category_id' => $product->category_id, 'slug' => $product->slug,
            'variants' => [['id' => $foreign->id, 'size' => '9', 'sku' => 'HIJACK', 'stock' => '0']],
        ]))->assertSessionHasErrors('variants.0.id');

        $this->assertSame(7, $foreign->fresh()->stock);
    }

    public function test_deleting_a_product_removes_it_and_clears_it_from_carts(): void
    {
        $variant = ProductVariant::factory()->create();
        $cart = Cart::create(['visitor_id' => (string) fake()->uuid(), 'status' => 'open']);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'added_via' => 'ui']);

        $productId = $variant->product_id;
        $this->delete("/admin/products/{$productId}")->assertRedirect('/admin/products');

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertSame(0, CartItem::count());
    }

    public function test_a_product_that_has_been_ordered_cannot_be_deleted(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = Order::create([
            'number' => 'STR-2026-000001', 'customer_name' => 'A', 'email' => 'a@example.com', 'address' => [],
            'subtotal_cents' => 100, 'shipping_cents' => 0, 'total_cents' => 100, 'currency' => 'USD', 'placed_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_variant_id' => $variant->id, 'product_name' => 'X', 'size' => 9, 'sku' => 'S',
            'unit_price_cents' => 100, 'quantity' => 1, 'line_total_cents' => 100,
        ]);

        $productId = $variant->product_id;
        $this->delete("/admin/products/{$productId}")->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $productId]);
    }

    public function test_product_search_treats_wildcards_literally(): void
    {
        Product::factory()->create(['name' => 'Alpha Runner']);
        Product::factory()->create(['name' => 'Beta Walker']);

        $this->get('/admin/products?q=alpha')->assertSee('Alpha Runner')->assertDontSee('Beta Walker');
        $this->get('/admin/products?q=%25')->assertDontSee('Alpha Runner');
    }
}
