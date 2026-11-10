<?php

namespace Tests\Feature\Shop;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class CartTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_adding_an_item_creates_a_cart_for_the_visitor(): void
    {
        $visitor = $this->asVisitor();
        $variant = $this->makeVariant(stock: 5);

        $this->from('/shoes')->post('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertRedirect('/shoes')->assertSessionHas('status');

        $cart = Cart::where('visitor_id', $visitor)->firstOrFail();
        $this->assertSame('open', $cart->status);
        $this->assertSame(2, $cart->items()->firstOrFail()->quantity);
    }

    public function test_json_add_returns_the_new_cart_count(): void
    {
        $this->asVisitor();
        $variant = $this->makeVariant(stock: 5);

        $this->jsonPost('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertOk()->assertJson(['cart' => ['count' => 1]]);
        $this->jsonPost('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertOk()->assertJson(['cart' => ['count' => 3]]);
    }

    public function test_the_header_badge_shows_the_cart_count(): void
    {
        $this->asVisitor();
        $variant = $this->makeVariant(stock: 5);
        $this->jsonPost('/cart/items', ['variant_id' => $variant->id, 'quantity' => 3]);

        $this->get('/shoes')->assertSee('data-cart-count', false)->assertSeeInOrder(['data-cart-count', '3']);
    }

    public function test_the_header_badge_is_hidden_when_the_cart_is_empty(): void
    {
        $this->asVisitor();

        // `hidden` and `inline-flex` both set display, so an empty badge must carry only `hidden`.
        $html = $this->get('/shoes')->getContent();
        preg_match('/<span data-cart-count class="([^"]*)"/', $html, $m);

        $this->assertStringContainsString('hidden', $m[1]);
        $this->assertStringNotContainsString('inline-flex', $m[1]);
    }

    public function test_a_sold_out_size_is_rejected_with_a_visible_message(): void
    {
        $this->asVisitor();
        $variant = $this->makeVariant(stock: 0);

        $this->from('/shoes')->post('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect('/shoes')->assertSessionHas('error');
        $this->followingRedirects()->get('/shoes')->assertSee('is sold out');

        $this->jsonPost('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'sold out'));
        $this->assertSame(0, CartItem::count());
    }

    public function test_quantity_cannot_exceed_stock_or_the_per_item_limit(): void
    {
        $this->asVisitor();
        $scarce = $this->makeVariant(stock: 2);
        $plenty = $this->makeVariant(stock: 20);

        $this->jsonPost('/cart/items', ['variant_id' => $scarce->id, 'quantity' => 2])->assertOk();
        $this->jsonPost('/cart/items', ['variant_id' => $scarce->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonPath('message', 'Only 2 of this size are available.');

        $this->jsonPost('/cart/items', ['variant_id' => $plenty->id, 'quantity' => 5])->assertOk();
        $this->jsonPost('/cart/items', ['variant_id' => $plenty->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonPath('message', 'You can order at most 5 of one size.');
    }

    public function test_inactive_products_cannot_be_added(): void
    {
        $this->asVisitor();
        $variant = $this->makeVariant(product: ['is_active' => false]);

        $this->jsonPost('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertStatus(422);
    }

    public function test_add_validates_input(): void
    {
        $this->asVisitor();

        $this->jsonPost('/cart/items', [])->assertStatus(422)->assertJsonValidationErrors(['variant_id', 'quantity']);
        $this->jsonPost('/cart/items', ['variant_id' => 999999, 'quantity' => 1])->assertStatus(422)->assertJsonValidationErrors('variant_id');
        $this->jsonPost('/cart/items', ['variant_id' => $this->makeVariant()->id, 'quantity' => 6])->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->jsonPost('/cart/items', ['variant_id' => 'abc', 'quantity' => 'x'])->assertStatus(422);
    }

    public function test_items_can_be_updated_and_removed(): void
    {
        $visitor = $this->asVisitor();
        $variant = $this->makeVariant(stock: 10);
        $item = CartItem::create(['cart_id' => $this->cartFor($visitor)->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'added_via' => 'ui']);

        $this->jsonPatch("/cart/items/{$item->id}", ['quantity' => 4])->assertOk()->assertJson(['cart' => ['count' => 4]]);
        $this->assertSame(4, $item->fresh()->quantity);

        $this->jsonPatch("/cart/items/{$item->id}", ['quantity' => 0])->assertStatus(422);
        $this->jsonPatch("/cart/items/{$item->id}", ['quantity' => 6])->assertStatus(422);

        $this->jsonDelete("/cart/items/{$item->id}")->assertOk()->assertJson(['cart' => ['count' => 0]]);
        $this->assertModelMissing($item);
    }

    public function test_another_visitors_items_cannot_be_changed(): void
    {
        $owner = (string) Str::uuid();
        $variant = $this->makeVariant(stock: 10);
        $item = CartItem::create(['cart_id' => $this->cartFor($owner)->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'added_via' => 'ui']);

        $this->asVisitor();
        $this->jsonPatch("/cart/items/{$item->id}", ['quantity' => 3])->assertNotFound();
        $this->jsonDelete("/cart/items/{$item->id}")->assertNotFound();

        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_the_cart_page_shows_items_and_server_side_totals(): void
    {
        $visitor = $this->asVisitor();
        $cart = $this->cartFor($visitor);
        $cheap = $this->makeVariant(stock: 5, priceDollars: 40, product: ['name' => 'Cheap Shoe']);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $cheap->id, 'quantity' => 2, 'added_via' => 'ui']);

        $this->get('/cart')->assertOk()->assertSee('Cheap Shoe')->assertSee('$80.00')->assertSee('$6.00')->assertSee('$86.00');
    }

    public function test_an_empty_cart_page_renders(): void
    {
        $this->asVisitor();

        $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
    }

    public function test_free_shipping_applies_from_the_threshold(): void
    {
        $visitor = $this->asVisitor();
        $variant = $this->makeVariant(stock: 5, priceDollars: 100);
        CartItem::create(['cart_id' => $this->cartFor($visitor)->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'added_via' => 'ui']);

        $this->get('/cart')->assertSee('Free')->assertSee('$100.00');
    }
}
