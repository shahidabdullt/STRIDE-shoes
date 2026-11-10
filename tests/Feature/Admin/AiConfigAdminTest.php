<?php

namespace Tests\Feature\Admin;

use App\Models\AiConfigVersion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\AiConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AiConfigAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'system_prompt' => 'You are the Stride assistant.',
            'voice_style_prompt' => 'Speak briefly.',
            'text_model' => 'gemini-3.6-flash',
            'live_model' => 'gemini-3.8-live',
            'live_voice' => '',
            'temperature' => '0.5',
            'max_output_tokens' => '800',
            'vad_silence_ms' => '900',
            'chat_enabled' => '1',
            'voice_enabled' => '0',
            'voice_max_seconds' => '600',
            'notes' => 'Test change',
            'activate' => '1',
            ...$overrides,
        ];
    }

    public function test_the_seeder_creates_one_active_version_and_does_not_overwrite_later(): void
    {
        $this->seed(AiConfigSeeder::class);
        $this->assertSame(1, AiConfigVersion::count());
        $this->assertSame(1, AiConfigVersion::active()->version);

        $this->seed(AiConfigSeeder::class);
        $this->assertSame(1, AiConfigVersion::count());
    }

    public function test_saving_creates_a_new_numbered_version_and_activates_it(): void
    {
        $this->seed(AiConfigSeeder::class);

        $this->actingAs($this->admin)->post('/admin/ai-config', $this->payload())->assertRedirect('/admin/ai-config');

        $this->assertSame(2, AiConfigVersion::count());
        $new = AiConfigVersion::where('version', 2)->firstOrFail();
        $this->assertTrue($new->is_active);
        $this->assertFalse($new->voice_enabled);
        $this->assertSame($this->admin->id, $new->created_by);
        $this->assertSame(1, AiConfigVersion::where('is_active', true)->count(), 'exactly one version is active');
        $this->assertFalse(AiConfigVersion::where('version', 1)->firstOrFail()->is_active);
    }

    public function test_saving_without_activating_keeps_the_current_version_active(): void
    {
        $this->seed(AiConfigSeeder::class);

        $this->actingAs($this->admin)->post('/admin/ai-config', $this->payload(['activate' => '0']));

        $this->assertSame(1, AiConfigVersion::active()->version);
        $this->assertSame(2, AiConfigVersion::count());
    }

    public function test_an_old_version_can_be_reactivated(): void
    {
        $this->seed(AiConfigSeeder::class);
        $this->actingAs($this->admin)->post('/admin/ai-config', $this->payload());
        $first = AiConfigVersion::where('version', 1)->firstOrFail();

        $this->post("/admin/ai-config/{$first->id}/activate")->assertRedirect('/admin/ai-config');

        $this->assertSame(1, AiConfigVersion::active()->version);
        $this->assertSame(1, AiConfigVersion::where('is_active', true)->count());
    }

    public function test_versions_are_immutable_except_for_the_active_flag(): void
    {
        $version = AiConfigVersion::factory()->create();

        $version->activate();
        $this->assertTrue($version->fresh()->is_active);

        $this->expectException(LogicException::class);
        $version->update(['system_prompt' => 'Changed']);
    }

    public function test_validation_and_model_allow_list(): void
    {
        $this->actingAs($this->admin);
        $bad = [
            'text_model' => 'gpt-9', 'live_model' => 'gemini-1.0', 'temperature' => '3', 'max_output_tokens' => '10',
            'vad_silence_ms' => '100', 'voice_max_seconds' => '5000', 'system_prompt' => '', 'live_voice' => 'a b!',
        ];

        $this->post('/admin/ai-config', $this->payload($bad))->assertSessionHasErrors(array_keys($bad));
        $this->assertSame(0, AiConfigVersion::count());
    }

    public function test_the_history_page_marks_the_active_version(): void
    {
        $this->seed(AiConfigSeeder::class);

        $this->actingAs($this->admin)->get('/admin/ai-config')->assertOk()->assertSee('Version 1')->assertSee('Active');
    }

    public function test_orders_are_listed_and_shown_read_only(): void
    {
        $order = Order::create([
            'number' => 'STR-2026-000042', 'customer_name' => 'Asha Rao', 'email' => 'asha@example.com',
            'address' => ['line1' => '12 Market St', 'city' => 'SF', 'state' => 'CA', 'postal_code' => '94105'],
            'subtotal_cents' => 10000, 'shipping_cents' => 0, 'total_cents' => 10000, 'currency' => 'USD', 'placed_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_name' => 'Pace 1 Men', 'size' => 9.5, 'sku' => 'PACE-9.5',
            'unit_price_cents' => 10000, 'quantity' => 1, 'line_total_cents' => 10000,
        ]);
        $this->actingAs($this->admin);

        $this->get('/admin/orders')->assertOk()->assertSee('STR-2026-000042')->assertSee('Asha Rao')->assertSee('$100.00');
        $this->get("/admin/orders/{$order->id}")->assertOk()->assertSee('Pace 1 Men')->assertSee('Size 9.5')->assertSee('asha@example.com');
        $this->put("/admin/orders/{$order->id}")->assertStatus(405);
        $this->delete("/admin/orders/{$order->id}")->assertStatus(405);
    }
}
