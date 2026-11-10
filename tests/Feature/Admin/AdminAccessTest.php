<?php

namespace Tests\Feature\Admin;

use App\Models\AiConfigVersion;
use App\Models\Category;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> method and URL for every admin screen, for a seeded record id 1 */
    private function screens(): array
    {
        Category::factory()->create(['id' => 1]);
        Product::factory()->create(['id' => 1, 'category_id' => 1]);
        Faq::factory()->create(['id' => 1]);
        KnowledgeArticle::factory()->create(['id' => 1]);
        AiConfigVersion::factory()->create(['id' => 1]);
        $order = new Order(['number' => 'STR-2026-000001', 'customer_name' => 'A', 'email' => 'a@example.com', 'address' => ['line1' => '1 A St', 'city' => 'SF', 'state' => 'CA', 'postal_code' => '94105'], 'subtotal_cents' => 0, 'shipping_cents' => 0, 'total_cents' => 0, 'currency' => 'USD', 'placed_at' => now()]);
        $order->id = 1;
        $order->save();

        return [
            ['get', '/admin'], ['get', '/admin/products'], ['get', '/admin/products/create'], ['get', '/admin/products/1/edit'],
            ['post', '/admin/products'], ['put', '/admin/products/1'], ['delete', '/admin/products/1'],
            ['get', '/admin/categories'], ['get', '/admin/categories/create'], ['get', '/admin/categories/1/edit'],
            ['post', '/admin/categories'], ['put', '/admin/categories/1'], ['delete', '/admin/categories/1'],
            ['get', '/admin/faqs'], ['get', '/admin/faqs/create'], ['get', '/admin/faqs/1/edit'],
            ['post', '/admin/faqs'], ['put', '/admin/faqs/1'], ['delete', '/admin/faqs/1'],
            ['get', '/admin/knowledge'], ['get', '/admin/knowledge/create'], ['get', '/admin/knowledge/1/edit'],
            ['post', '/admin/knowledge'], ['put', '/admin/knowledge/1'], ['delete', '/admin/knowledge/1'],
            ['get', '/admin/ai-config'], ['post', '/admin/ai-config'], ['post', '/admin/ai-config/1/activate'],
            ['get', '/admin/orders'], ['get', '/admin/orders/1'],
        ];
    }

    public function test_guests_are_sent_to_the_login_page_from_every_admin_screen(): void
    {
        foreach ($this->screens() as [$method, $url]) {
            $this->{$method}($url)->assertRedirect('/admin/login');
        }
    }

    public function test_non_admin_users_get_403_on_every_admin_screen(): void
    {
        $user = User::factory()->create();

        foreach ($this->screens() as [$method, $url]) {
            $this->actingAs($user)->{$method}($url)->assertForbidden();
        }
    }

    public function test_admin_screens_that_only_read_load_for_an_admin(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ($this->screens() as [$method, $url]) {
            if ($method === 'get') {
                $this->actingAs($admin)->get($url)->assertOk();
            }
        }
    }
}
