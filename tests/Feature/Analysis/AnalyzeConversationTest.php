<?php

namespace Tests\Feature\Analysis;

use App\Analysis\ConversationAnalyzer;
use App\Jobs\AnalyzeConversation;
use App\Models\AiConfigVersion;
use App\Models\Conversation;
use App\Models\DataPointDefinition;
use App\Models\EvaluationCriterion;
use App\Models\User;
use Database\Seeders\AiConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\FakesGemini;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AnalyzeConversationTest extends TestCase
{
    use FakesGemini, MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiConfigSeeder::class);
    }

    private function conversation(array $messages, string $channel = 'text'): Conversation
    {
        $conversation = Conversation::factory()->create(['channel' => $channel, 'status' => 'ended', 'ai_config_version_id' => AiConfigVersion::active()->id]);
        foreach ($messages as $message) {
            $conversation->messages()->create($message);
        }

        return $conversation;
    }

    /** The three classic questions from AC-A7. */
    private function mixedConversation(): Conversation
    {
        return $this->conversation([
            ['role' => 'user', 'content' => 'Is the Pace 1 in size 9?'],
            ['role' => 'tool', 'content' => 'check_size_availability', 'tool_name' => 'check_size_availability', 'tool_args' => ['slug' => 'pace-1-men-black', 'size' => 9], 'tool_result' => ['in_stock' => true]],
            ['role' => 'assistant', 'content' => 'Yes, size 9 is in stock.'],
            ['role' => 'user', 'content' => 'Do you sell gift cards?'],
            ['role' => 'assistant', 'content' => "I'm not sure, sorry."],
            ['role' => 'user', 'content' => 'Tell me a joke.'],
            ['role' => 'assistant', 'content' => 'I can only help with shoes.'],
        ]);
    }

    private function questionsResponse(): array
    {
        return $this->geminiText(json_encode(['questions' => [
            ['question' => 'Is the Pace 1 in size 9?', 'topic' => 'Sizing', 'outcome' => 'answered', 'source' => 'catalog', 'reason' => 'Tool confirmed stock.'],
            ['question' => 'Do you sell gift cards?', 'topic' => 'gift cards', 'outcome' => 'not_answered', 'source' => 'none', 'reason' => 'No source.'],
            ['question' => 'Tell me a joke.', 'topic' => 'off-topic', 'outcome' => 'out_of_scope', 'source' => 'none', 'reason' => 'Unrelated.'],
            ['question' => 'Invalid one', 'topic' => 'x', 'outcome' => 'maybe', 'source' => 'none', 'reason' => 'bad enum'],
        ]]));
    }

    // The outcome rules themselves are unit-tested in tests/Unit/OutcomeCalculatorTest.php.

    public function test_answered_unanswered_and_off_topic_give_a_failed_conversation(): void
    {
        $conversation = $this->mixedConversation();
        $this->fakeGemini([$this->questionsResponse()]);

        (new AnalyzeConversation($conversation))->handle(app(ConversationAnalyzer::class));

        $conversation->refresh();
        $this->assertSame(['answered', 'not_answered', 'out_of_scope'], $conversation->questions->pluck('outcome')->all(), 'the invalid row is dropped');
        $this->assertSame('sizing', $conversation->questions->first()->topic);
        $this->assertSame(['failed', 'knowledge_gap', 3, 1, 'done'], [$conversation->outcome, $conversation->failure_kind, $conversation->questions_total, $conversation->questions_unanswered, $conversation->analysis_status]);

        $body = $this->sentGeminiBodies()[0];
        $this->assertSame('application/json', $body['response_format']['mime_type']);
        $this->assertStringContainsString('[tool check_size_availability', $body['input'][0]['content'][0]['text']);
    }

    public function test_data_points_and_evaluations_are_validated_against_their_definitions(): void
    {
        $size = DataPointDefinition::factory()->create(['key' => 'shoe_size', 'type' => 'number']);
        $activity = DataPointDefinition::factory()->create(['key' => 'activity', 'label' => 'Activity', 'type' => 'enum', 'options' => ['running', 'trail']]);
        $waterproof = DataPointDefinition::factory()->create(['key' => 'wants_waterproof', 'label' => 'Waterproof', 'type' => 'boolean']);
        $prices = EvaluationCriterion::factory()->create(['scale' => 'pass_fail']);
        $helpful = EvaluationCriterion::factory()->create(['name' => 'Helpful', 'scale' => 'score_1_5']);
        $voiceOnly = EvaluationCriterion::factory()->create(['name' => 'Voice only', 'applies_to' => 'voice']);

        $this->fakeGemini([
            $this->questionsResponse(),
            $this->geminiText(json_encode(['data_points' => [
                ['key' => 'shoe_size', 'value' => '9.5', 'evidence' => 'I wear a 9.5'],
                ['key' => 'activity', 'value' => 'skiing', 'evidence' => 'not an option'],
                ['key' => 'wants_waterproof', 'value' => 'yes', 'evidence' => 'needs to be waterproof'],
                ['key' => 'unknown_key', 'value' => 'x', 'evidence' => ''],
            ]])),
            $this->geminiText(json_encode(['evaluations' => [
                ['criterion_id' => $prices->id, 'passed' => true, 'rationale' => 'All prices from tools.'],
                ['criterion_id' => $helpful->id, 'score' => 9, 'rationale' => 'Out of range.'],
                ['criterion_id' => $voiceOnly->id, 'passed' => false, 'rationale' => 'Not applicable to text.'],
            ]])),
        ]);

        (new AnalyzeConversation($this->mixedConversation()))->handle(app(ConversationAnalyzer::class));

        $conversation = Conversation::latest('id')->first();
        $points = $conversation->dataPoints->keyBy('data_point_definition_id');
        $this->assertSame('9.50', (string) $points[$size->id]->value_number);
        $this->assertTrue($points[$waterproof->id]->value_bool);
        $this->assertFalse($points->has($activity->id), 'an enum value outside the options is dropped');
        $this->assertSame('I wear a 9.5', $points[$size->id]->evidence);

        $this->assertSame([$prices->id], $conversation->evaluations->pluck('evaluation_criterion_id')->all());
        $this->assertTrue($conversation->evaluations->first()->passed);
        $this->assertSame('gemini-3.6-flash', $conversation->evaluations->first()->evaluator_model);

        // The evaluation prompt only lists criteria for this channel.
        $this->assertStringNotContainsString('Voice only', $this->sentGeminiBodies()[2]['system_instruction']);
    }

    public function test_a_failing_optional_call_still_finishes_the_analysis(): void
    {
        DataPointDefinition::factory()->create();
        $this->fakeGemini([$this->questionsResponse(), [400, ['error' => ['message' => 'bad schema']]]]);

        (new AnalyzeConversation($this->mixedConversation()))->handle(app(ConversationAnalyzer::class));

        $conversation = Conversation::latest('id')->first();
        $this->assertSame('done', $conversation->analysis_status);
        $this->assertStringContainsString('data points: bad_request', $conversation->analysis_error);
        $this->assertSame('failed', $conversation->outcome);
    }

    public function test_a_live_gap_report_wins_and_is_not_duplicated(): void
    {
        $conversation = $this->mixedConversation();
        $conversation->questions()->create(['question' => 'Do you sell gift cards?', 'topic' => 'gift cards', 'outcome' => 'not_answered', 'source' => 'none', 'reason' => 'Reported live.', 'detected_by' => 'model_report']);
        $this->fakeGemini([$this->geminiText(json_encode(['questions' => [
            ['question' => 'Do you sell gift cards?', 'topic' => 'gift cards', 'outcome' => 'answered', 'source' => 'knowledge', 'reason' => 'Classifier disagrees.'],
        ]]))]);

        (new AnalyzeConversation($conversation))->handle(app(ConversationAnalyzer::class));

        $conversation->refresh();
        $this->assertSame(['model_report'], $conversation->questions->pluck('detected_by')->all());
        $this->assertSame('failed', $conversation->outcome);
    }

    public function test_rerunning_replaces_previous_results_and_keeps_a_manual_override(): void
    {
        $conversation = $this->mixedConversation();
        $admin = User::factory()->create(['is_admin' => true]);
        $conversation->forceFill(['outcome' => 'success', 'outcome_overridden_by' => $admin->id])->save();
        $this->fakeGemini([$this->questionsResponse(), $this->questionsResponse()]);

        (new AnalyzeConversation($conversation))->handle(app(ConversationAnalyzer::class));
        (new AnalyzeConversation($conversation))->handle(app(ConversationAnalyzer::class));

        $conversation->refresh();
        $this->assertCount(3, $conversation->questions);
        $this->assertSame('success', $conversation->outcome, 'the override is kept');
        $this->assertSame(1, $conversation->questions_unanswered, 'the counts still update');
    }

    public function test_a_conversation_without_customer_messages_is_skipped(): void
    {
        Http::fake();
        $conversation = $this->conversation([]);

        (new AnalyzeConversation($conversation))->handle(app(ConversationAnalyzer::class));

        $this->assertSame(['skipped', 'pending'], [$conversation->fresh()->analysis_status, $conversation->fresh()->outcome]);
        Http::assertNothingSent();
    }

    public function test_when_the_analysis_fails_a_known_failure_is_still_recorded(): void
    {
        $withError = $this->conversation([
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Sorry', 'status' => 'error', 'error_code' => 'timeout'],
        ]);
        $clean = $this->conversation([['role' => 'user', 'content' => 'Hi'], ['role' => 'assistant', 'content' => 'Hello']]);

        (new AnalyzeConversation($withError))->failed(new RuntimeException('quota'));
        (new AnalyzeConversation($clean))->failed(new RuntimeException('quota'));

        $this->assertSame(['failed', 'failed', 'technical'], [$withError->fresh()->analysis_status, $withError->fresh()->outcome, $withError->fresh()->failure_kind]);
        $this->assertSame(['failed', 'pending'], [$clean->fresh()->analysis_status, $clean->fresh()->outcome], 'never a success without the classifier');
    }

    public function test_ending_a_conversation_queues_its_analysis_once(): void
    {
        Queue::fake();
        $this->asVisitor();
        $uuid = $this->jsonPost('/assistant/conversations', ['device_class' => 'desktop'])->json('uuid');

        $this->jsonPost("/assistant/conversations/{$uuid}/end")->assertNoContent();
        $this->jsonPost("/assistant/conversations/{$uuid}/end")->assertNoContent();

        Queue::assertPushed(AnalyzeConversation::class, 1);
    }

    public function test_idle_conversations_are_closed_and_queued(): void
    {
        Queue::fake();
        config(['assistant.idle_minutes' => 15]);
        $idle = Conversation::factory()->create(['last_activity_at' => now()->subMinutes(16)]);
        $recent = Conversation::factory()->create(['last_activity_at' => now()->subMinutes(5)]);

        $this->artisan('assistant:close-idle')->assertSuccessful();

        $this->assertSame(['ended', 'idle'], [$idle->fresh()->status, $idle->fresh()->ended_reason]);
        $this->assertSame('active', $recent->fresh()->status);
        Queue::assertPushed(AnalyzeConversation::class, fn ($job) => $job->conversation->is($idle));
        Queue::assertPushed(AnalyzeConversation::class, 1);
    }
}
