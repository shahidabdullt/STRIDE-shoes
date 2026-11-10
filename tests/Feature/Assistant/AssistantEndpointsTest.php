<?php

namespace Tests\Feature\Assistant;

use App\Models\AiConfigVersion;
use App\Models\Conversation;
use Database\Seeders\AiConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesGemini;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AssistantEndpointsTest extends TestCase
{
    use FakesGemini, MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiConfigSeeder::class);
        Cache::flush();
    }

    private function start(string $device = 'desktop'): string
    {
        return $this->jsonPost('/assistant/conversations', ['device_class' => $device])->assertCreated()->json('uuid');
    }

    public function test_starting_a_conversation_records_visitor_channel_device_and_config_version(): void
    {
        $visitor = $this->asVisitor();

        $response = $this->jsonPost('/assistant/conversations', ['device_class' => 'mobile'])->assertCreated();

        $response->assertJsonStructure(['uuid', 'greeting']);
        $conversation = Conversation::sole();
        $this->assertSame(
            [$visitor, 'text', 'mobile', 'active', AiConfigVersion::active()->id],
            [$conversation->visitor_id, $conversation->channel, $conversation->device_class, $conversation->status, $conversation->ai_config_version_id],
        );
    }

    public function test_device_class_is_validated(): void
    {
        $this->asVisitor();
        $this->jsonPost('/assistant/conversations', ['device_class' => 'tablet'])->assertStatus(422)->assertJsonValidationErrors('device_class');
        $this->assertSame(0, Conversation::count());
    }

    /** @return array<string, array{0: mixed}> */
    public static function invalidMessages(): array
    {
        return ['missing' => [null], 'blank' => ['   '], 'too long' => [str_repeat('a', 1001)], 'not a string' => [['x']]];
    }

    #[DataProvider('invalidMessages')]
    public function test_messages_are_validated(mixed $message): void
    {
        Http::fake();
        $this->asVisitor();
        $uuid = $this->start();

        $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => $message])
            ->assertStatus(422)->assertJsonValidationErrors('message');
        Http::assertNothingSent();
    }

    public function test_another_visitors_conversation_is_a_404_everywhere(): void
    {
        Http::fake();
        $this->asVisitor();
        $uuid = $this->start();

        $this->asVisitor();
        $this->get("/assistant/conversations/{$uuid}", ['Accept' => 'application/json'])->assertNotFound();
        $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => 'hi'])->assertNotFound();
        $this->jsonPost("/assistant/conversations/{$uuid}/end")->assertNotFound();
        $this->assertSame('active', Conversation::sole()->status);
        Http::assertNothingSent();
    }

    public function test_a_voice_conversation_cannot_be_used_on_the_text_endpoints(): void
    {
        $visitor = $this->asVisitor();
        $voice = Conversation::factory()->voice()->create(['visitor_id' => $visitor]);

        $this->get("/assistant/conversations/{$voice->uuid}", ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_an_unknown_uuid_is_a_404(): void
    {
        $this->asVisitor();
        $this->get('/assistant/conversations/'.Str::uuid(), ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_restore_returns_only_customer_and_assistant_messages(): void
    {
        $this->asVisitor();
        $uuid = $this->start();
        $conversation = Conversation::sole();
        $conversation->messages()->createMany([
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'tool', 'content' => 'view_cart', 'tool_name' => 'view_cart'],
            ['role' => 'assistant', 'content' => 'Hello!'],
        ]);

        $this->get("/assistant/conversations/{$uuid}", ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.content', 'Hi')
            ->assertJsonPath('messages.1.role', 'assistant');
    }

    public function test_ending_a_conversation_stops_further_messages(): void
    {
        Http::fake();
        $this->asVisitor();
        $uuid = $this->start();

        $this->jsonPost("/assistant/conversations/{$uuid}/end")->assertNoContent();

        $conversation = Conversation::sole();
        $this->assertSame(['ended', 'user_ended'], [$conversation->status, $conversation->ended_reason]);
        $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => 'hi'])->assertStatus(409)->assertJsonPath('error', 'conversation_ended');
        Http::assertNothingSent();
    }

    public function test_the_21st_message_in_a_minute_is_rate_limited(): void
    {
        $this->fakeGemini(array_fill(0, 20, $this->geminiText('ok')));
        $this->asVisitor();
        $uuid = $this->start();

        for ($i = 0; $i < 20; $i++) {
            $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => "m{$i}"])->assertOk();
        }

        $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => 'one too many'])
            ->assertStatus(429)->assertJsonPath('error', 'rate_limited')->assertJsonStructure(['message']);

        // The limit is per visitor: someone else can still chat.
        $this->asVisitor();
        $this->jsonPost('/assistant/conversations', ['device_class' => 'desktop'])->assertCreated();
    }

    public function test_when_the_admin_disables_chat_the_api_returns_503_and_the_widget_shows_it(): void
    {
        Http::fake();
        $this->asVisitor();
        $uuid = $this->start();
        $this->disableChat();

        $this->jsonPost('/assistant/conversations', ['device_class' => 'desktop'])->assertStatus(503)->assertJsonPath('error', 'assistant_unavailable');
        $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => 'hi'])->assertStatus(503);
        Http::assertNothingSent();

        $this->withoutVite()->get('/')->assertOk()->assertSee('data-enabled="0"', false);
    }

    public function test_the_daily_global_cap_returns_503(): void
    {
        config(['assistant.daily_chat_cap' => 1]);
        $this->fakeGemini([$this->geminiText('ok')]);
        $this->asVisitor();
        $uuid = $this->start();

        $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => 'first'])->assertOk();
        $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => 'second'])->assertStatus(503);
    }

    public function test_the_access_code_gate(): void
    {
        config(['assistant.access_code' => 'open-sesame']);
        $this->asVisitor();

        $this->jsonPost('/assistant/conversations', ['device_class' => 'desktop'])
            ->assertForbidden()->assertJsonPath('error', 'access_code_required');

        $this->jsonPost('/assistant/access', ['code' => 'wrong'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->jsonPost('/assistant/access', ['code' => 'open-sesame'])->assertNoContent();

        $this->jsonPost('/assistant/conversations', ['device_class' => 'desktop'])->assertCreated();
    }

    public function test_access_attempts_are_limited_to_five_a_minute(): void
    {
        config(['assistant.access_code' => 'open-sesame']);
        $this->asVisitor();

        for ($i = 0; $i < 5; $i++) {
            $this->jsonPost('/assistant/access', ['code' => "guess{$i}"])->assertStatus(422);
        }

        $this->jsonPost('/assistant/access', ['code' => 'open-sesame'])->assertStatus(429);
    }

    public function test_without_a_configured_code_the_gate_is_off(): void
    {
        config(['assistant.access_code' => null]);
        $this->asVisitor();

        $this->jsonPost('/assistant/access', ['code' => 'anything'])->assertNotFound();
        $this->jsonPost('/assistant/conversations', ['device_class' => 'desktop'])->assertCreated();
    }

    public function test_the_widget_is_on_storefront_pages_but_not_in_the_admin(): void
    {
        $this->withoutVite();

        $this->get('/')->assertSee('data-chat-launcher', false)->assertSee('data-enabled="1"', false);
        $this->get('/shoes')->assertSee('data-chat-launcher', false);
        $this->get('/admin/login')->assertDontSee('data-chat-launcher', false);
    }

    private function disableChat(): void
    {
        $current = AiConfigVersion::active();
        $copy = $current->replicate(['is_active', 'version']);
        $copy->version = AiConfigVersion::nextVersionNumber();
        $copy->chat_enabled = false;
        $copy->save();
        $copy->activate();
    }
}
