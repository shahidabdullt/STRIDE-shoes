<?php

namespace Tests\Concerns;

use App\Http\Middleware\EnsureVisitorId;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait MakesShopData
{
    /** Acts as one browser: the same visitor cookie is sent on every following request. */
    protected function asVisitor(?string $id = null): string
    {
        $id ??= (string) Str::uuid();
        $this->withCookie(EnsureVisitorId::COOKIE, $id);

        return $id;
    }

    /*
     * Laravel's postJson()/patchJson()/deleteJson() do not send the cookies set with withCookie(),
     * so the visitor would change on every call. These send the same JSON-style request
     * (Accept: application/json) through the ordinary methods, which do keep the cookie.
     */
    protected function jsonPost(string $uri, array $data = []): TestResponse
    {
        return $this->post($uri, $data, ['Accept' => 'application/json']);
    }

    protected function jsonPatch(string $uri, array $data = []): TestResponse
    {
        return $this->patch($uri, $data, ['Accept' => 'application/json']);
    }

    protected function jsonDelete(string $uri, array $data = []): TestResponse
    {
        return $this->delete($uri, $data, ['Accept' => 'application/json']);
    }

    /** @param array<string, mixed> $product */
    protected function makeVariant(int $stock = 5, float $size = 9, int $priceDollars = 100, array $product = []): ProductVariant
    {
        $product = Product::factory()->create(['price_cents' => $priceDollars * 100, ...$product]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'size' => $size, 'stock' => $stock]);
    }

    protected function cartFor(string $visitorId): Cart
    {
        return Cart::create(['visitor_id' => $visitorId, 'status' => 'open']);
    }

    /** @return array<string, string> */
    protected function validCheckout(array $overrides = []): array
    {
        return [
            'customer_name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'address_line1' => '12 Market Street',
            'address_line2' => '',
            'city' => 'San Francisco',
            'state' => 'ca',
            'postal_code' => '94105',
            ...$overrides,
        ];
    }
}
