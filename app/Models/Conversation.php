<?php

namespace App\Models;

use App\Jobs\AnalyzeConversation;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid', 'channel', 'voice_mode', 'device_class', 'visitor_id', 'cart_id', 'ai_config_version_id', 'status',
    'ended_reason', 'started_at', 'last_activity_at', 'ended_at', 'message_count', 'duration_seconds',
    'input_tokens', 'output_tokens',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, Prunable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
            'message_count' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    /** Messages, questions and data cascade with the conversation, so this is the whole retention policy. */
    public function prunable(): Builder
    {
        return static::query()->where('last_activity_at', '<', now()->subDays((int) config('assistant.retention_days')));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Voice sessions are hard-stopped server-side too: once the configured maximum (plus a minute's grace
     * for the browser to wind down) has passed, the session no longer accepts tool calls or tokens.
     */
    public function voiceTimeIsUp(): bool
    {
        $max = (int) $this->aiConfigVersion->voice_max_seconds;

        return $this->started_at->copy()->addSeconds($max + 60)->isPast();
    }

    /** Ends an active conversation once (later calls keep the first reason) and queues its analysis. */
    public function end(string $reason, array $attributes = []): void
    {
        if (! $this->isActive()) {
            return;
        }

        $this->forceFill([
            'status' => 'ended',
            'ended_reason' => $reason,
            'ended_at' => now(),
            'last_activity_at' => now(),
            ...$attributes,
        ])->save();

        AnalyzeConversation::dispatch($this)->afterCommit();
    }

    /** @return HasMany<ConversationDataPoint, $this> */
    public function dataPoints(): HasMany
    {
        return $this->hasMany(ConversationDataPoint::class);
    }

    /** @return HasMany<ConversationEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(ConversationEvaluation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function outcomeOverrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_overridden_by');
    }

    /** @return BelongsTo<AiConfigVersion, $this> */
    public function aiConfigVersion(): BelongsTo
    {
        return $this->belongsTo(AiConfigVersion::class);
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        // Time first: voice transcripts are stored after the tool calls they led to, with the time they were spoken.
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<ConversationQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(ConversationQuestion::class)->orderBy('id');
    }
}
