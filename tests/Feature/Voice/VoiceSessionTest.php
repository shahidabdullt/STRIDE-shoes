<?php

namespace Tests\Feature\Voice;

use App\Assistant\LiveSessionConfig;
use App\Models\AiConfigVersion;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\Message;
use Database\Seeders\AiConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class VoiceSessionTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private const TOKENS_URL = 'https://generativelanguage.googleapis.com/v1beta/auth_tokens';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiConfigSeeder::class);
        Cache::flush();
        config(['services.gemini.key' => 'AIzaTEST-not-real']);
    }

    private function fakeToken(): void
    {
        Http::fake([self::TOKENS_URL => Http::response(['name' => 'auth_tokens/abc123'])]);
    }

    private function start(string $mode = 'hands_free')
    {
        return $this->jsonPost('/voice/sessions', ['mode' => $mode, 'device_class' => 'desktop']);
    }

    public function test_the_locked_setup_differs_by_mode_and_never_sends_forbidden_fields(): void
    {
        $config = AiConfigVersion::active();
        $live = app(LiveSessionConfig::class);

        $handsFree = $live->setup($config, 'hands_free');
        $hold = $live->setup($config, 'hold_to_talk');

        $this->assertSame(['disabled' => false, 'silenceDurationMs' => $config->vad_silence_ms], $handsFree['realtimeInputConfig']['automaticActivityDetection']);
        $this->assertSame(['disabled' => true], $hold['realtimeInputConfig']['automaticActivityDetection']);
        $this->assertSame(['AUDIO'], $handsFree['generationConfig']['responseModalities']);
        $this->assertSame('models/gemini-3.8-live', $handsFree['model']);

        $json = json_encode($handsFree);
        $this->assertStringNotContainsString('thinkingConfig', $json);
        $this->assertStringNotContainsString('proactiveAudio', $json);
        $this->assertArrayNotHasKey('sessionResumption', $handsFree, 'left to the browser so it can resume');

        $declarations = $handsFree['tools'][0]['functionDeclarations'];
        $this->assertCount(8, $declarations);
        $this->assertTrue(collect($declarations)->every(fn ($d) => $d['behavior'] === 'BLOCKING'));
        $this->assertSame('OBJECT', collect($declarations)->firstWhere('name', 'add_to_cart')['parameters']['type']);
        $this->assertSame('NUMBER', collect($declarations)->firstWhere('name', 'add_to_cart')['parameters']['properties']['size']['type']);
        $this->assertArrayNotHasKey('parameters', collect($declarations)->firstWhere('name', 'view_cart'));

        $this->assertStringContainsString('stay silent', $handsFree['systemInstruction']['parts'][0]['text']);
        $this->assertSame(array_keys($handsFree), explode(',', $live->fieldMask($handsFree)));
    }

    public function test_starting_mints_a_single_use_locked_token_and_never_returns_the_key(): void
    {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $this->fakeToken();
        $this->asVisitor();

        $response = $this->start('hold_to_talk')->assertCreated();

        $response->assertJson([
            'token' => 'auth_tokens/abc123',
            'mode' => 'hold_to_talk',
            'model' => 'models/gemini-3.8-live',
            'max_seconds' => 600,
        ]);
        $this->assertStringNotContainsString('AIza', $response->getContent());
        $this->assertStringStartsWith('wss://generativelanguage.googleapis.com/ws/', $response->json('ws_url'));

        Http::assertSent(function (Request $request) {
            $expire = Carbon::parse($request['expireTime']);
            $newSession = Carbon::parse($request['newSessionExpireTime']);

            return $request->url() === self::TOKENS_URL
                && $request->hasHeader('x-goog-api-key', 'AIzaTEST-not-real')
                && $request['uses'] === 1
                && $newSession->diffInSeconds(now()) <= 60
                && $expire->diffInSeconds(now()) <= 900
                && $request['bidiGenerateContentSetup']['realtimeInputConfig']['automaticActivityDetection']['disabled'] === true
                && str_contains($request['fieldMask'], 'systemInstruction')
                && str_contains($request['fieldMask'], 'tools');
        });

        $conversation = Conversation::sole();
        $this->assertSame(['voice', 'hold_to_talk', 'active'], [$conversation->channel, $conversation->voice_mode, $conversation->status]);
    }

    public function test_a_token_failure_returns_503_and_marks_the_conversation_failed(): void
    {
        Http::fake([self::TOKENS_URL => Http::response(['error' => ['message' => 'quota', 'code' => 'resource_exhausted']], 429)]);
        $this->asVisitor();

        $this->start()->assertStatus(503)->assertJsonPath('error', 'assistant_unavailable');
        $this->assertSame(['failed', 'error'], [Conversation::sole()->status, Conversation::sole()->ended_reason]);
    }

    public function test_validation_kill_switch_cap_and_rate_limit(): void
    {
        $this->fakeToken();
        $this->asVisitor();

        $this->jsonPost('/voice/sessions', ['mode' => 'shout', 'device_class' => 'desktop'])->assertStatus(422)->assertJsonValidationErrors('mode');

        for ($i = 0; $i < 4; $i++) {
            $this->start()->assertCreated();
        }
        $this->start()->assertStatus(429);

        config(['assistant.daily_voice_session_cap' => 4]);
        $this->asVisitor();
        $this->start()->assertStatus(503);

        config(['assistant.daily_voice_session_cap' => 100]);
        $copy = AiConfigVersion::active()->replicate(['is_active', 'version']);
        $copy->version = AiConfigVersion::nextVersionNumber();
        $copy->voice_enabled = false;
        $copy->save();
        $copy->activate();
        $this->asVisitor();
        $this->start()->assertStatus(503);
        $this->withoutVite()->get('/')->assertSee('data-voice-launcher', false)->assertSee('data-enabled="0"', false);
    }

    public function test_the_access_code_gate_covers_voice(): void
    {
        config(['assistant.access_code' => 'open-sesame']);
        Http::fake();
        $this->asVisitor();

        $this->start()->assertForbidden()->assertJsonPath('error', 'access_code_required');
        Http::assertNothingSent();
    }

    public function test_tool_calls_run_through_the_registry_for_the_owner_only(): void
    {
        $this->fakeToken();
        $visitor = $this->asVisitor();
        $uuid = $this->start()->json('conversation_uuid');
        $variant = $this->makeVariant(stock: 4, size: 10);

        $this->jsonPost("/voice/sessions/{$uuid}/tool-calls", ['call_id' => 'c1', 'name' => 'add_to_cart', 'args' => ['slug' => $variant->product->slug, 'size' => 10]])
            ->assertOk()->assertJsonPath('response.added', true)->assertJsonPath('cart.count', 1);

        $tool = Message::where('role', 'tool')->sole();
        $this->assertSame(['add_to_cart', 'client', 'ok'], [$tool->tool_name, $tool->source, $tool->status]);
        $this->assertSame('voice', CartItem::sole()->added_via);

        $this->jsonPost("/voice/sessions/{$uuid}/tool-calls", ['call_id' => 'c2', 'name' => 'place_order', 'args' => []])->assertStatus(422);
        $this->jsonPost("/voice/sessions/{$uuid}/tool-calls", ['call_id' => 'c3', 'name' => 'add_to_cart', 'args' => ['slug' => 'x', 'size' => 'big']])
            ->assertOk()->assertJsonPath('response.error', fn ($e) => str_contains($e, 'Invalid arguments'));

        $this->asVisitor();
        $this->jsonPost("/voice/sessions/{$uuid}/tool-calls", ['call_id' => 'c4', 'name' => 'view_cart', 'args' => []])->assertNotFound();
        $this->assertSame($visitor, Conversation::sole()->visitor_id);
    }

    public function test_text_conversations_cannot_use_voice_endpoints(): void
    {
        $visitor = $this->asVisitor();
        $text = Conversation::factory()->create(['visitor_id' => $visitor]);

        $this->jsonPost("/voice/sessions/{$text->uuid}/tool-calls", ['call_id' => 'c', 'name' => 'view_cart'])->assertNotFound();
    }

    public function test_transcripts_are_stored_redacted_as_client_reported(): void
    {
        $this->fakeToken();
        $this->asVisitor();
        $uuid = $this->start()->json('conversation_uuid');

        $this->jsonPost("/voice/sessions/{$uuid}/transcripts", ['segments' => [
            ['role' => 'user', 'text' => 'My card is 4242 4242 4242 4242'],
            ['role' => 'assistant', 'text' => 'Please never share that.'],
        ]])->assertNoContent();

        $messages = Message::orderBy('id')->get();
        $this->assertSame(['user', 'assistant'], $messages->pluck('role')->all());
        $this->assertTrue($messages->every(fn ($m) => $m->source === 'client'));
        $this->assertStringNotContainsString('4242 4242', $messages[0]->content);
        $this->assertSame(2, Conversation::sole()->message_count);

        $this->jsonPost("/voice/sessions/{$uuid}/transcripts", ['segments' => array_fill(0, 51, ['role' => 'user', 'text' => 'x'])])->assertStatus(422);
        $this->jsonPost("/voice/sessions/{$uuid}/transcripts", ['segments' => [['role' => 'system', 'text' => 'x']]])->assertStatus(422);
        $this->jsonPost("/voice/sessions/{$uuid}/transcripts", ['segments' => [['role' => 'user', 'text' => str_repeat('a', 2001)]]])->assertStatus(422);
    }

    public function test_ending_records_reason_and_clamps_reported_duration(): void
    {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $this->fakeToken();
        $this->asVisitor();
        $uuid = $this->start()->json('conversation_uuid');
        Carbon::setTestNow('2026-11-08 10:02:00');

        $this->jsonPost("/voice/sessions/{$uuid}/end", ['reason' => 'backgrounded', 'duration_seconds' => 5000, 'usage' => ['input_tokens' => 900, 'output_tokens' => 300]])->assertNoContent();

        $c = Conversation::sole();
        $this->assertSame(['ended', 'backgrounded', 125, 900, 300], [$c->status, $c->ended_reason, $c->duration_seconds, $c->input_tokens, $c->output_tokens]);

        // Ended: no more tool calls, but a final transcript batch is still accepted briefly.
        $this->jsonPost("/voice/sessions/{$uuid}/tool-calls", ['call_id' => 'c', 'name' => 'view_cart'])->assertStatus(409);
        $this->jsonPost("/voice/sessions/{$uuid}/transcripts", ['segments' => [['role' => 'user', 'text' => 'bye']]])->assertNoContent();
        Carbon::setTestNow('2026-11-08 10:10:00');
        $this->jsonPost("/voice/sessions/{$uuid}/transcripts", ['segments' => [['role' => 'user', 'text' => 'late']]])->assertStatus(409);
        $this->jsonPost("/voice/sessions/{$uuid}/end", ['reason' => 'nonsense'])->assertStatus(422);
    }

    public function test_transcripts_keep_the_time_they_were_spoken_so_the_transcript_reads_in_order(): void
    {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $this->fakeToken();
        $this->asVisitor();
        $uuid = $this->start()->json('conversation_uuid');

        Carbon::setTestNow('2026-11-08 10:00:05');
        $this->jsonPost("/voice/sessions/{$uuid}/tool-calls", ['call_id' => 'c1', 'name' => 'view_cart'])->assertOk();

        Carbon::setTestNow('2026-11-08 10:00:09');
        $this->jsonPost("/voice/sessions/{$uuid}/transcripts", ['segments' => [
            ['role' => 'user', 'text' => "What's in my cart?", 'at' => '2026-11-08T10:00:02Z'],
            ['role' => 'assistant', 'text' => 'Your cart is empty.', 'at' => '2026-11-08T10:00:06Z'],
            ['role' => 'user', 'text' => 'Spoofed future time', 'at' => '2030-01-01T00:00:00Z'],
        ]])->assertNoContent();

        $this->assertSame(['user', 'tool', 'assistant', 'user'], Conversation::sole()->messages->pluck('role')->all());
        $this->assertSame('2026-11-08 10:00:09', Conversation::sole()->messages->last()->created_at->toDateTimeString(), 'times outside the session are clamped');
    }

    public function test_sessions_are_hard_stopped_after_the_maximum(): void
    {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $this->fakeToken();
        $this->asVisitor();
        $uuid = $this->start()->json('conversation_uuid');

        Carbon::setTestNow('2026-11-08 10:11:01'); // 600 s max + 60 s grace has passed

        $this->jsonPost("/voice/sessions/{$uuid}/tool-calls", ['call_id' => 'c', 'name' => 'view_cart'])->assertStatus(409);
        $this->assertSame('max_duration', Conversation::sole()->ended_reason);
        $this->jsonPost("/voice/sessions/{$uuid}/token")->assertStatus(409);
    }
}
