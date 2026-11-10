<?php

namespace Tests\Feature\Assistant;

use App\Models\AiConfigVersion;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\ConversationQuestion;
use App\Models\Message;
use App\Models\Order;
use Database\Seeders\AiConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGemini;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/** The text chat loop (BLUEPRINT §2.4) through the real HTTP endpoint, with Gemini faked. */
class ChatTurnTest extends TestCase
{
    use FakesGemini, MakesShopData, RefreshDatabase;

    private string $visitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiConfigSeeder::class);
        $this->visitor = $this->asVisitor();
    }

    private function startConversation(): string
    {
        return $this->jsonPost('/assistant/conversations', ['device_class' => 'mobile'])->assertCreated()->json('uuid');
    }

    private function say(string $uuid, string $message)
    {
        return $this->jsonPost("/assistant/conversations/{$uuid}/messages", ['message' => $message]);
    }

    public function test_a_plain_answer_is_returned_and_stored(): void
    {
        $this->fakeGemini([$this->geminiText('Hi! How can I help?')]);
        $uuid = $this->startConversation();

        $this->say($uuid, 'Hello')->assertOk()
            ->assertJson(['reply' => 'Hi! How can I help?', 'cart' => ['count' => 0], 'products' => []])
            ->assertJsonMissing(['error' => 'assistant_unavailable']);

        $conversation = Conversation::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
        $assistant = $conversation->messages->last();
        $this->assertSame('ok', $assistant->status);
        $this->assertSame(100, $assistant->input_tokens);
        $this->assertSame(30, $assistant->output_tokens, 'thought tokens count as output');
        $this->assertNotNull($assistant->latency_ms);
        $this->assertSame(2, $conversation->message_count);
        $this->assertSame(100, $conversation->input_tokens);
    }

    public function test_the_request_carries_prompt_tools_config_and_history(): void
    {
        $this->fakeGemini([$this->geminiText('First answer'), $this->geminiText('Second answer')]);
        $uuid = $this->startConversation();
        $this->say($uuid, 'First question');
        $this->say($uuid, 'Second question');

        $body = $this->sentGeminiBodies()[1];
        $config = AiConfigVersion::active();

        $this->assertSame($config->text_model, $body['model']);
        $this->assertFalse($body['store']);
        $this->assertStringContainsString('Reply in the same language the customer writes in', $body['system_instruction']);
        $this->assertStringContainsString('report_knowledge_gap', $body['system_instruction']);
        $this->assertSame((float) $config->temperature, (float) $body['generation_config']['temperature']);
        $this->assertCount(8, $body['tools']);
        $this->assertSame(
            ['user_input', 'model_output', 'user_input'],
            array_column($body['input'], 'type'),
        );
        $this->assertSame('Second question', $body['input'][2]['content'][0]['text']);
    }

    public function test_one_tool_round_runs_the_tool_and_returns_product_cards(): void
    {
        $variant = $this->makeVariant(stock: 6, size: 9, priceDollars: 129, product: ['name' => 'Summit Trail', 'activity' => 'trail']);
        $this->fakeGemini([
            $this->geminiCalls([['search_products', ['activity' => 'trail', 'size' => 9]]]),
            $this->geminiText('The Summit Trail is $129.00 and in stock in size 9.'),
        ]);
        $uuid = $this->startConversation();

        $response = $this->say($uuid, 'Trail shoes in size 9?')->assertOk();

        $response->assertJsonPath('products.0.slug', $variant->product->slug)
            ->assertJsonPath('products.0.price', '$129.00');

        $tool = Message::where('role', 'tool')->sole();
        $this->assertSame('search_products', $tool->tool_name);
        $this->assertEquals(['activity' => 'trail', 'size' => 9], $tool->tool_args); // MySQL JSON reorders keys
        $this->assertSame(1, $tool->tool_result['count']);

        // Round 2 echoes the model's steps, then our function_result with the same call id.
        $input = $this->sentGeminiBodies()[1]['input'];
        $types = array_column($input, 'type');
        $this->assertSame(['user_input', 'thought', 'function_call', 'function_result'], $types);
        $this->assertSame($input[2]['id'], $input[3]['call_id']);
        $this->assertSame('sig', $input[1]['signature'], 'thought signatures are passed back unchanged');
        $this->assertStringContainsString('Summit Trail', $input[3]['result'][0]['text']);
    }

    public function test_three_tool_rounds_then_an_answer(): void
    {
        $variant = $this->makeVariant(stock: 2, size: 9.5);
        $slug = $variant->product->slug;
        $this->fakeGemini([
            $this->geminiCalls([['check_size_availability', ['slug' => $slug, 'size' => 9]]]),
            $this->geminiCalls([['check_size_availability', ['slug' => $slug, 'size' => 9.5]]]),
            $this->geminiCalls([['get_product', ['slug' => $slug]]]),
            $this->geminiText('Size 9.5 is available, only a few left.'),
        ]);

        $this->say($this->startConversation(), 'Is it in 9 or 9.5?')->assertOk()
            ->assertJsonPath('reply', 'Size 9.5 is available, only a few left.');

        $results = Message::where('role', 'tool')->orderBy('id')->pluck('tool_result');
        $this->assertFalse($results[0]['in_stock']);
        $this->assertSame('low', $results[1]['quantity_band']);
        $this->assertCount(4, $this->sentGeminiBodies());
    }

    public function test_the_tool_round_cap_stops_the_loop_with_a_fallback(): void
    {
        $call = $this->geminiCalls([['view_cart', []]]);
        $this->fakeGemini([$call, $call, $call, $call, $this->geminiText('never reached')]);

        $this->say($this->startConversation(), 'Loop forever')->assertOk()
            ->assertJsonPath('error', 'assistant_unavailable')
            ->assertJsonPath('reply', fn (string $r) => str_contains($r, route('faqs.index')));

        $this->assertCount(4, $this->sentGeminiBodies());
        $this->assertSame('tool_round_limit', Message::where('role', 'assistant')->sole()->error_code);
    }

    public function test_malformed_and_unknown_calls_are_answered_with_errors_the_model_can_recover_from(): void
    {
        $this->fakeGemini([
            $this->geminiCalls([['check_size_availability', ['slug' => 'x', 'size' => 'huge']], ['place_order', ['confirm' => true]], ['view_cart', 'not-an-object']]),
            $this->geminiText('Sorry, let me try that differently.'),
        ]);

        $this->say($this->startConversation(), 'Buy it now')->assertOk()->assertJsonMissingPath('error');

        $tools = Message::where('role', 'tool')->orderBy('id')->get();
        $this->assertSame(['invalid_arguments', 'unknown_tool', 'invalid_arguments'], $tools->pluck('error_code')->all());
        $this->assertTrue($tools->every(fn (Message $m) => $m->status === 'error'));
        $this->assertSame(0, Order::count(), 'no tool can create an order');

        $results = array_values(array_filter($this->sentGeminiBodies()[1]['input'], fn ($s) => $s['type'] === 'function_result'));
        $this->assertCount(3, $results);
        $this->assertStringContainsString('error', $results[1]['result'][0]['text']);
    }

    public function test_a_gemini_failure_returns_a_friendly_fallback_and_is_stored_as_an_error(): void
    {
        $this->fakeGemini([[500, ['error' => ['message' => 'boom']]], [500, ['error' => ['message' => 'boom']]]]);

        $this->say($this->startConversation(), 'Hello')->assertOk()
            ->assertJsonPath('error', 'assistant_unavailable')
            ->assertJsonPath('reply', fn (string $r) => str_contains($r, 'help page'));

        $assistant = Message::where('role', 'assistant')->sole();
        $this->assertSame('error', $assistant->status);
        $this->assertSame('unavailable', $assistant->error_code);
    }

    public function test_a_timeout_returns_the_fallback(): void
    {
        config(['services.gemini.key' => 'test-key-not-real']);
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->say($this->startConversation(), 'Hello')->assertOk()->assertJsonPath('error', 'assistant_unavailable');
        $this->assertSame('timeout', Message::where('role', 'assistant')->sole()->error_code);
    }

    public function test_failed_replies_are_left_out_of_later_history(): void
    {
        $this->fakeGemini([[400, ['error' => ['message' => 'bad']]], $this->geminiText('Now it works')]);
        $uuid = $this->startConversation();
        $this->say($uuid, 'First');
        $this->say($uuid, 'Second');

        $this->assertSame(['user_input', 'user_input'], array_column($this->sentGeminiBodies()[1]['input'], 'type'));
    }

    public function test_an_incomplete_reply_with_text_is_shown_and_flagged(): void
    {
        $this->fakeGemini([$this->geminiText('Partial answer', 'incomplete')]);

        $this->say($this->startConversation(), 'Hi')->assertOk()->assertJsonPath('reply', 'Partial answer')->assertJsonMissingPath('error');
        $this->assertSame('incomplete', Message::where('role', 'assistant')->sole()->error_code);
    }

    public function test_adding_to_the_cart_through_chat_updates_the_cart_count(): void
    {
        $variant = $this->makeVariant(stock: 5, size: 10);
        $this->fakeGemini([
            $this->geminiCalls([['add_to_cart', ['slug' => $variant->product->slug, 'size' => 10, 'quantity' => 2]]]),
            $this->geminiText('Added two pairs.'),
        ]);
        $uuid = $this->startConversation();

        $this->say($uuid, 'Add two in size 10')->assertOk()->assertJsonPath('cart.count', 2);

        $item = CartItem::where('product_variant_id', $variant->id)->sole();
        $this->assertSame('chat', $item->added_via);
        $this->assertSame($this->visitor, $item->cart->visitor_id);
        $this->assertSame($item->cart_id, Conversation::where('uuid', $uuid)->value('cart_id'));
    }

    public function test_a_reported_knowledge_gap_is_recorded_for_this_conversation(): void
    {
        $this->fakeGemini([
            $this->geminiCalls([['report_knowledge_gap', ['question' => 'Do you ship to Iceland?', 'topic' => 'Shipping']]]),
            $this->geminiText("I don't know if we ship to Iceland. Please check our help page."),
        ]);
        $uuid = $this->startConversation();

        $this->say($uuid, 'Do you ship to Iceland?')->assertOk();

        $gap = ConversationQuestion::sole();
        $this->assertSame(Conversation::where('uuid', $uuid)->value('id'), $gap->conversation_id);
        $this->assertSame(['not_answered', 'model_report', 'shipping'], [$gap->outcome, $gap->detected_by, $gap->topic]);
    }

    public function test_card_numbers_are_masked_before_storage_and_before_gemini(): void
    {
        $this->fakeGemini([$this->geminiText('Please never share card details.')]);

        $this->say($this->startConversation(), 'My card is 4242 4242 4242 4242, order STR-2026-000123')->assertOk();

        $stored = Message::where('role', 'user')->value('content');
        $this->assertStringNotContainsString('4242 4242', $stored);
        $this->assertStringContainsString('STR-2026-000123', $stored);
        $this->assertStringNotContainsString('4242 4242', json_encode($this->sentGeminiBodies()));
    }

    public function test_an_order_is_attributed_to_the_visitors_last_conversation(): void
    {
        $this->fakeGemini([$this->geminiText('ok')]);
        $uuid = $this->startConversation();
        $variant = $this->makeVariant(stock: 3);
        $this->jsonPost('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();

        $this->post('/checkout', $this->validCheckout())->assertRedirect();

        $this->assertSame(Conversation::where('uuid', $uuid)->value('id'), Order::sole()->conversation_id);
    }
}
