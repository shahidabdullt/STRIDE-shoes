<?php

namespace Tests\Feature\Shop;

use App\Actions\Checkout\PlaceOrder;
use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function fillCart(string $visitor, $variant, int $quantity = 1): Cart
    {
        $cart = Cart::firstOrCreate(['visitor_id' => $visitor, 'status' => 'open']);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => $quantity, 'added_via' => 'ui']);

        return $cart;
    }

    public function test_checkout_redirects_to_the_cart_when_it_is_empty(): void
    {
        $this->asVisitor();

        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHas('error');
        $this->post('/checkout', $this->validCheckout())->assertRedirect('/cart');
        $this->assertSame(0, Order::count());
    }

    public function test_the_checkout_page_shows_the_simulated_payment_banner_and_mobile_friendly_fields(): void
    {
        $visitor = $this->asVisitor();
        $this->fillCart($visitor, $this->makeVariant());

        $this->get('/checkout')->assertOk()
            ->assertSee('Payment simulated')
            ->assertSee('autocomplete="email"', false)
            ->assertSee('inputmode="email"', false)
            ->assertSee('autocomplete="postal-code"', false);
    }

    public function test_placing_an_order_snapshots_prices_decrements_stock_and_closes_the_cart(): void
    {
        $visitor = $this->asVisitor();
        $variant = $this->makeVariant(stock: 5, priceDollars: 59, product: ['name' => 'Canvas Daily']);
        $cart = $this->fillCart($visitor, $variant, 2);

        $response = $this->post('/checkout', $this->validCheckout());

        $order = Order::with('items')->firstOrFail();
        $this->assertMatchesRegularExpression('/^STR-\d{4}-\d{6}$/', $order->number);
        $this->assertSame(11800, $order->subtotal_cents);
        $this->assertSame(0, $order->shipping_cents);
        $this->assertSame(11800, $order->total_cents);
        $this->assertSame('simulated', $order->payment_status);
        $this->assertSame('CA', $order->address['state']);
        $this->assertSame('US', $order->address['country']);

        $line = $order->items->firstOrFail();
        $this->assertSame('Canvas Daily', $line->product_name);
        $this->assertSame(5900, $line->unit_price_cents);
        $this->assertSame(11800, $line->line_total_cents);

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame('converted', $cart->fresh()->status);

        $response->assertRedirect();
        $this->assertStringContainsString('signature=', $response->headers->get('Location'));
    }

    public function test_shipping_is_charged_below_the_threshold(): void
    {
        $visitor = $this->asVisitor();
        $this->fillCart($visitor, $this->makeVariant(priceDollars: 59));

        $this->post('/checkout', $this->validCheckout());

        $order = Order::firstOrFail();
        $this->assertSame(600, $order->shipping_cents);
        $this->assertSame(6500, $order->total_cents);
    }

    public function test_tampered_price_fields_have_no_effect(): void
    {
        $visitor = $this->asVisitor();
        $this->fillCart($visitor, $this->makeVariant(priceDollars: 120));

        $this->post('/checkout', $this->validCheckout([
            'price_cents' => 1, 'total_cents' => 1, 'subtotal_cents' => 1, 'shipping_cents' => 0, 'unit_price_cents' => 1, 'total' => '0.01',
        ]));

        $order = Order::firstOrFail();
        $this->assertSame(12000, $order->subtotal_cents);
        $this->assertSame(12000, $order->total_cents);
        $this->assertSame(12000, $order->items->firstOrFail()->unit_price_cents);
    }

    public function test_checkout_validates_the_customer_fields(): void
    {
        $visitor = $this->asVisitor();
        $this->fillCart($visitor, $this->makeVariant());

        $this->post('/checkout', [])->assertSessionHasErrors(['customer_name', 'email', 'address_line1', 'city', 'state', 'postal_code']);
        $this->post('/checkout', $this->validCheckout(['email' => 'nope']))->assertSessionHasErrors('email');
        $this->post('/checkout', $this->validCheckout(['state' => 'California']))->assertSessionHasErrors('state');
        $this->post('/checkout', $this->validCheckout(['postal_code' => 'ABCDE']))->assertSessionHasErrors('postal_code');
        $this->assertSame(0, Order::count());
    }

    public function test_the_confirmation_page_needs_a_valid_signature(): void
    {
        $visitor = $this->asVisitor();
        $this->fillCart($visitor, $this->makeVariant());
        $location = $this->post('/checkout', $this->validCheckout())->headers->get('Location');
        $order = Order::firstOrFail();

        $this->get($location)->assertOk()->assertSee($order->number)->assertSee('Asha Rao')->assertSee('nothing was charged');

        $this->get(route('orders.confirmation', $order))->assertForbidden();
        $this->get($location.'x')->assertForbidden();
        $this->get(URL::temporarySignedRoute('orders.confirmation', now()->subMinute(), ['order' => $order->number]))->assertForbidden();
    }

    public function test_a_second_checkout_of_the_same_cart_is_refused(): void
    {
        $visitor = $this->asVisitor();
        $variant = $this->makeVariant(stock: 5);
        $cart = $this->fillCart($visitor, $variant);

        $this->post('/checkout', $this->validCheckout());

        $this->expectException(CartException::class);
        app(PlaceOrder::class)($cart->fresh(), $this->customer());
    }

    public function test_the_last_unit_can_only_be_bought_once(): void
    {
        $variant = $this->makeVariant(stock: 1);
        $first = $this->fillCart('11111111-1111-4111-8111-111111111111', $variant);
        $second = $this->fillCart('22222222-2222-4222-8222-222222222222', $variant);

        app(PlaceOrder::class)($first, $this->customer());

        try {
            app(PlaceOrder::class)($second, $this->customer());
            $this->fail('The second checkout should have been refused.');
        } catch (CartException $e) {
            $this->assertStringContainsString('no longer available', $e->getMessage());
        }

        $this->assertSame(1, Order::count());
        $this->assertSame(0, $variant->fresh()->stock);
        $this->assertSame('open', $second->fresh()->status);
    }

    public function test_stock_rows_are_locked_for_update_during_checkout(): void
    {
        $variant = $this->makeVariant(stock: 3);
        $cart = $this->fillCart('11111111-1111-4111-8111-111111111111', $variant);

        DB::enableQueryLog();
        app(PlaceOrder::class)($cart, $this->customer());
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertTrue($queries->contains(fn ($q) => str_contains($q, 'from `product_variants`') && str_contains($q, 'for update')));
        $this->assertTrue($queries->contains(fn ($q) => str_contains($q, 'from `carts`') && str_contains($q, 'for update')));
    }

    public function test_a_failed_checkout_changes_nothing(): void
    {
        $visitor = $this->asVisitor();
        $enough = $this->makeVariant(stock: 5);
        $short = $this->makeVariant(stock: 1);
        $cart = $this->fillCart($visitor, $enough, 2);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $short->id, 'quantity' => 2, 'added_via' => 'ui']);

        $this->post('/checkout', $this->validCheckout())->assertRedirect('/cart')->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $enough->fresh()->stock);
        $this->assertSame(1, $short->fresh()->stock);
        $this->assertSame('open', $cart->fresh()->status);
    }

    public function test_a_deactivated_product_blocks_checkout(): void
    {
        $visitor = $this->asVisitor();
        $variant = $this->makeVariant();
        $this->fillCart($visitor, $variant);
        $variant->product->update(['is_active' => false]);

        $this->post('/checkout', $this->validCheckout())->assertRedirect('/cart')->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    /** @return array{customer_name: string, email: string, address: array<string, string>} */
    private function customer(): array
    {
        return [
            'customer_name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'address' => ['line1' => '12 Market Street', 'line2' => '', 'city' => 'San Francisco', 'state' => 'CA', 'postal_code' => '94105', 'country' => 'US'],
        ];
    }
}
