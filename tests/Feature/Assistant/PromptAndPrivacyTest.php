<?php

namespace Tests\Feature\Assistant;

use App\Assistant\PromptBuilder;
use App\Models\AiConfigVersion;
use App\Models\Conversation;
use App\Models\KnowledgeArticle;
use App\Support\Redactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_prompt_has_admin_text_fixed_rules_and_always_included_knowledge(): void
    {
        $config = AiConfigVersion::factory()->create(['system_prompt' => 'ADMIN PROMPT', 'voice_style_prompt' => 'VOICE STYLE']);
        KnowledgeArticle::factory()->create(['title' => 'Returns', 'body' => 'Free returns within 30 days.', 'always_include' => true]);
        KnowledgeArticle::factory()->create(['title' => 'Draft', 'body' => 'UNPUBLISHED', 'always_include' => true, 'is_published' => false]);
        KnowledgeArticle::factory()->create(['title' => 'Care', 'body' => 'NOT ALWAYS', 'always_include' => false]);

        $text = app(PromptBuilder::class)->build($config, 'text');
        $voice = app(PromptBuilder::class)->build($config, 'voice');

        $this->assertStringStartsWith('ADMIN PROMPT', $text);
        $this->assertStringContainsString('Reply in the same language the customer writes in', $text);
        $this->assertStringContainsString('call report_knowledge_gap', $text);
        $this->assertStringContainsString('Free returns within 30 days.', $text);
        $this->assertStringNotContainsString('UNPUBLISHED', $text);
        $this->assertStringNotContainsString('NOT ALWAYS', $text);
        $this->assertStringNotContainsString('VOICE STYLE', $text);
        $this->assertStringContainsString('VOICE STYLE', $voice);
    }

    public function test_only_luhn_valid_card_numbers_are_masked(): void
    {
        $this->assertSame('pay with [card number removed] please', Redactor::redact('pay with 4242-4242-4242-4242 please'));
        $this->assertSame('[card number removed]', Redactor::redact('4111111111111111'));
        // Not Luhn-valid, an order number and a phone-length number stay.
        $this->assertSame('1234 5678 9012 3456', Redactor::redact('1234 5678 9012 3456'));
        $this->assertSame('Order STR-2026-000123, size 9.5, $129.00', Redactor::redact('Order STR-2026-000123, size 9.5, $129.00'));
    }

    public function test_emails_are_masked_only_when_configured(): void
    {
        config(['assistant.redact_emails' => true]);
        $this->assertSame('mail me at [email removed] thanks', Redactor::redact('mail me at asha.rao+shoes@example.co.in thanks'));

        config(['assistant.redact_emails' => false]);
        $this->assertSame('asha@example.com', Redactor::redact('asha@example.com'));
    }

    public function test_conversations_past_retention_are_pruned_with_their_messages(): void
    {
        config(['assistant.retention_days' => 30]);
        $old = Conversation::factory()->create(['last_activity_at' => now()->subDays(31)]);
        $old->messages()->create(['role' => 'user', 'content' => 'old']);
        $recent = Conversation::factory()->create(['last_activity_at' => now()->subDays(29)]);

        $this->artisan('model:prune', ['--model' => Conversation::class])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertDatabaseMissing('messages', ['content' => 'old']);
    }
}
