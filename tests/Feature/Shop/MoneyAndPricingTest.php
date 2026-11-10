<?php

namespace Tests\Feature\Shop;

use App\Support\Money;
use App\Support\Pricing;
use Tests\TestCase;

class MoneyAndPricingTest extends TestCase
{
    public function test_money_formats_minor_units(): void
    {
        $this->assertSame('$119.00', Money::format(11900));
        $this->assertSame('$5.99', Money::format(599));
        $this->assertSame('$0.00', Money::format(0));
    }

    public function test_the_currency_comes_from_config(): void
    {
        config(['store.currency' => 'INR']);

        $this->assertStringContainsString('119.00', Money::format(11900));
        $this->assertStringNotContainsString('$', Money::format(11900));
    }

    public function test_sizes_drop_a_trailing_zero(): void
    {
        $this->assertSame('9', Money::size('9.0'));
        $this->assertSame('9.5', Money::size(9.5));
        $this->assertSame('10', Money::size(10));
    }

    public function test_shipping_rule(): void
    {
        $this->assertSame(0, Pricing::shippingCents(0));
        $this->assertSame(600, Pricing::shippingCents(5900));
        $this->assertSame(600, Pricing::shippingCents(9999));
        $this->assertSame(0, Pricing::shippingCents(10000));
        $this->assertSame(['subtotal_cents' => 5900, 'shipping_cents' => 600, 'total_cents' => 6500], Pricing::totals(5900));
    }
}
