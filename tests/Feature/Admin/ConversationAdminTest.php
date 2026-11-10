<?php

namespace Tests\Feature\Admin;

use App\Jobs\AnalyzeConversation;
use App\Models\AiConfigVersion;
use App\Models\Conversation;
use App\Models\ConversationQuestion;
use App\Models\DataPointDefinition;
use App\Models\EvaluationCriterion;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\AiConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(AiConfigSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Asha Admin']);
    }

    private function conversation(array $attributes = []): Conversation
    {
        return Conversation::factory()->create(['ai_config_version_id' => AiConfigVersion::active()->id, 'status' => 'ended', ...$attributes]);
    }

    private function gap(Conversation $conversation, string $question = 'Do you sell gift cards?', string $topic = 'gift cards'): ConversationQuestion
    {
        return $conversation->questions()->create(['question' => $question, 'topic' => $topic, 'outcome' => 'not_answered', 'source' => 'none', 'reason' => 'x', 'detected_by' => 'classifier']);
    }

    public function test_new_admin_screens_require_an_admin(): void
    {
        $conversation = $this->conversation();
        $urls = ['/admin/conversations', "/admin/conversations/{$conversation->id}", '/admin/knowledge-gaps', '/admin/data-points', '/admin/evaluation-criteria'];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->actingAs(User::factory()->create());
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->delete("/admin/conversations/{$conversation->id}")->assertForbidden();

        $this->actingAs($this->admin);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_list_filters_by_outcome_channel_and_date(): void
    {
        $this->conversation(['outcome' => 'success', 'channel' => 'text', 'started_at' => '2026-11-01 10:00:00', 'device_class' => 'mobile']);
        $failedVoice = $this->conversation(['outcome' => 'failed', 'failure_kind' => 'knowledge_gap', 'channel' => 'voice', 'started_at' => '2026-11-05 10:00:00']);
        $this->gap($failedVoice, 'Do you have a store in Chicago?', 'stores');
        $this->actingAs($this->admin);

        $ids = fn (string $query) => $this->get('/admin/conversations?'.$query)->assertOk()->viewData('conversations')->pluck('id')->all();

        $this->assertCount(2, $ids(''));
        $this->assertSame([$failedVoice->id], $ids('outcome=failed'));
        $this->assertSame([$failedVoice->id], $ids('channel=voice&failure_kind=knowledge_gap'));
        $this->assertSame([$failedVoice->id], $ids('from=2026-11-03&to=2026-11-30'));
        $this->assertSame([$failedVoice->id], $ids('q=chicago'));
        $this->assertCount(1, $ids('device_class=mobile'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function invalidFilters(): array
    {
        return [
            'outcome' => ['outcome=great', 'outcome'],
            'channel' => ['channel=fax', 'channel'],
            'failure kind' => ['failure_kind=both', 'failure_kind'],
            'date' => ['from=yesterday', 'from'],
            'range' => ['from=2026-11-05&to=2026-11-01', 'to'],
            'sql' => ['outcome=success%27%20OR%201%3D1--', 'outcome'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_redirect_with_errors(string $query, string $field): void
    {
        $this->actingAs($this->admin)->get('/admin/conversations?'.$query)
            ->assertRedirect(route('admin.conversations.index'))->assertSessionHasErrors($field);
    }

    public function test_the_detail_page_shows_transcript_questions_data_points_and_evaluations(): void
    {
        $conversation = $this->conversation(['channel' => 'voice', 'outcome' => 'failed', 'analysis_status' => 'done']);
        $conversation->messages()->createMany([
            ['role' => 'user', 'content' => 'Trail shoes in size 10?', 'source' => 'client'],
            ['role' => 'tool', 'content' => 'search_products', 'tool_name' => 'search_products', 'tool_args' => ['size' => 10], 'tool_result' => ['count' => 2], 'source' => 'client'],
            ['role' => 'assistant', 'content' => 'We have two.', 'source' => 'client'],
        ]);
        $this->gap($conversation);
        $definition = DataPointDefinition::factory()->create(['label' => 'Shoe size']);
        $conversation->dataPoints()->create(['data_point_definition_id' => $definition->id, 'value_number' => 10, 'evidence' => 'size 10']);
        $criterion = EvaluationCriterion::factory()->create(['name' => 'Only quoted catalog prices']);
        $conversation->evaluations()->create(['evaluation_criterion_id' => $criterion->id, 'passed' => true, 'rationale' => 'Fine.', 'evaluator_model' => 'gemini-3.6-flash']);

        $this->actingAs($this->admin)->get("/admin/conversations/{$conversation->id}")->assertOk()
            ->assertSee('Trail shoes in size 10?')
            ->assertSee('client-reported')
            ->assertSee('search_products')
            ->assertSee('Do you sell gift cards?')
            ->assertSee('not answered')
            ->assertSee('Shoe size')
            ->assertSee('Only quoted catalog prices')
            ->assertSee('pass');
    }

    public function test_an_admin_can_override_the_outcome_and_return_to_automatic(): void
    {
        $conversation = $this->conversation(['outcome' => 'failed']);
        $this->gap($conversation);
        $this->actingAs($this->admin);

        $this->patch("/admin/conversations/{$conversation->id}/outcome", ['outcome' => 'success'])->assertRedirect();
        $this->assertSame(['success', $this->admin->id], [$conversation->fresh()->outcome, $conversation->fresh()->outcome_overridden_by]);
        $this->get("/admin/conversations/{$conversation->id}")->assertSee('Overridden by Asha Admin');

        $this->patch("/admin/conversations/{$conversation->id}/outcome", ['outcome' => 'automatic'])->assertRedirect();
        $this->assertSame(['failed', null], [$conversation->fresh()->outcome, $conversation->fresh()->outcome_overridden_by]);

        $this->patch("/admin/conversations/{$conversation->id}/outcome", ['outcome' => 'great'])->assertSessionHasErrors('outcome');
    }

    public function test_re_running_analysis_queues_the_job_only_for_ended_conversations(): void
    {
        Queue::fake();
        $ended = $this->conversation(['analysis_status' => 'failed']);
        $active = $this->conversation(['status' => 'active']);
        $this->actingAs($this->admin);

        $this->post("/admin/conversations/{$ended->id}/analyze")->assertRedirect()->assertSessionHas('status');
        $this->post("/admin/conversations/{$active->id}/analyze")->assertRedirect()->assertSessionHas('error');

        Queue::assertPushed(AnalyzeConversation::class, 1);
        $this->assertSame('pending', $ended->fresh()->analysis_status);
    }

    public function test_deleting_a_conversation_removes_everything_recorded_about_it(): void
    {
        $conversation = $this->conversation();
        $conversation->messages()->create(['role' => 'user', 'content' => 'hi']);
        $this->gap($conversation);
        $definition = DataPointDefinition::factory()->create();
        $conversation->dataPoints()->create(['data_point_definition_id' => $definition->id, 'value_number' => 9]);
        $criterion = EvaluationCriterion::factory()->create();
        $conversation->evaluations()->create(['evaluation_criterion_id' => $criterion->id, 'passed' => true, 'rationale' => 'x', 'evaluator_model' => 'm']);

        $this->actingAs($this->admin)->delete("/admin/conversations/{$conversation->id}")->assertRedirect('/admin/conversations');

        foreach (['conversations', 'messages', 'conversation_questions', 'conversation_data_points', 'conversation_evaluations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_knowledge_gaps_are_grouped_by_topic_and_an_faq_resolves_them(): void
    {
        $a = $this->gap($this->conversation(), 'Do you sell gift cards?', 'gift cards');
        $this->gap($this->conversation(), 'Can I buy a gift card?', 'gift cards');
        $this->gap($this->conversation(), 'Is there a store in Chicago?', 'stores');
        $this->actingAs($this->admin);

        $topics = $this->get('/admin/knowledge-gaps')->assertOk()->assertSee('Create FAQ from this')->viewData('topics');
        $this->assertSame([['gift cards', 2], ['stores', 1]], $topics->map(fn ($t) => [$t['topic'], $t['count']])->all());

        // "Create FAQ from this" opens a pre-filled form...
        $this->get("/admin/knowledge-gaps/{$a->id}/faq")->assertRedirect(route('admin.faqs.create', ['gap' => $a->id]));
        $this->get(route('admin.faqs.create', ['gap' => $a->id]))->assertOk()
            ->assertSee('value="Do you sell gift cards?"', false)
            ->assertSee('value="gift cards"', false)
            ->assertSee('name="from_gap"', false);

        // ...and saving it resolves the gap and links the FAQ.
        $this->post('/admin/faqs', [
            'question' => 'Do you sell gift cards?', 'answer' => 'Not yet.', 'topic' => 'gift cards', 'is_published' => '1', 'from_gap' => $a->id,
        ])->assertRedirect(route('admin.knowledge-gaps.index'));

        $faq = Faq::where('question', 'Do you sell gift cards?')->sole();
        $this->assertNotNull($a->fresh()->resolved_at);
        $this->assertSame($faq->id, $a->fresh()->resolved_by_faq_id);
        $this->assertSame(1, $this->get('/admin/knowledge-gaps')->viewData('topics')->firstWhere('topic', 'gift cards')['count']);
        $this->assertSame(1, $this->get('/admin/knowledge-gaps?resolved=yes')->viewData('topics')->sum('count'));
    }

    public function test_a_gap_can_be_marked_resolved_without_an_faq(): void
    {
        $gap = $this->gap($this->conversation());

        $this->actingAs($this->admin)->post("/admin/knowledge-gaps/{$gap->id}/resolve")->assertRedirect();

        $this->assertNotNull($gap->fresh()->resolved_at);
        $this->assertNull($gap->fresh()->resolved_by_faq_id);
    }

    public function test_data_point_definitions_are_validated(): void
    {
        $this->actingAs($this->admin);
        $valid = ['key' => 'shoe_size', 'label' => 'Shoe size', 'instruction' => 'Size.', 'type' => 'number', 'options' => '', 'is_active' => '1'];

        $this->post('/admin/data-points', [...$valid, 'key' => 'Shoe Size!'])->assertSessionHasErrors('key');
        $this->post('/admin/data-points', [...$valid, 'type' => 'enum', 'options' => 'only-one'])->assertSessionHasErrors('options');
        $this->post('/admin/data-points', [...$valid, 'key' => 'activity', 'type' => 'enum', 'options' => 'running, trail , walking'])->assertRedirect('/admin/data-points');
        $this->assertSame(['running', 'trail', 'walking'], DataPointDefinition::where('key', 'activity')->value('options'));

        $this->post('/admin/data-points', $valid)->assertRedirect('/admin/data-points');
        $this->post('/admin/data-points', $valid)->assertSessionHasErrors('key');

        // The type cannot change once values exist.
        $definition = DataPointDefinition::where('key', 'shoe_size')->sole();
        $this->conversation()->dataPoints()->create(['data_point_definition_id' => $definition->id, 'value_number' => 9]);
        $this->put("/admin/data-points/{$definition->id}", [...$valid, 'type' => 'string'])->assertSessionHasErrors('type');
        $this->put("/admin/data-points/{$definition->id}", [...$valid, 'label' => 'US size'])->assertRedirect('/admin/data-points');
        $this->assertSame('US size', $definition->fresh()->label);
    }

    public function test_evaluation_criteria_crud(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/evaluation-criteria', ['name' => 'Polite', 'rubric' => 'Is polite.', 'scale' => 'stars', 'applies_to' => 'all', 'is_active' => '1'])->assertSessionHasErrors('scale');
        $this->post('/admin/evaluation-criteria', ['name' => 'Polite', 'rubric' => 'Is polite.', 'scale' => 'score_1_5', 'applies_to' => 'voice', 'is_active' => '1'])->assertRedirect('/admin/evaluation-criteria');

        $criterion = EvaluationCriterion::sole();
        $this->get('/admin/evaluation-criteria')->assertSee('Polite')->assertSee('voice only');
        $this->delete("/admin/evaluation-criteria/{$criterion->id}")->assertRedirect('/admin/evaluation-criteria');
        $this->assertModelMissing($criterion);
    }

    public function test_the_dashboard_shows_success_rates_and_top_gaps(): void
    {
        $this->conversation(['outcome' => 'success', 'channel' => 'text', 'started_at' => now()]);
        $failed = $this->conversation(['outcome' => 'failed', 'channel' => 'text', 'started_at' => now()]);
        $this->conversation(['outcome' => 'pending', 'channel' => 'voice', 'started_at' => now()]);
        $this->gap($failed);

        $response = $this->actingAs($this->admin)->get('/admin')->assertOk();

        $this->assertSame(['total' => 2, 'rate' => 50.0], $response->viewData('rates')['text']);
        $this->assertSame(1, $response->viewData('pending'));
        $response->assertSee('gift cards')->assertSee('Do you sell gift cards?');
    }
}
