<?php

namespace App\Models;

use Database\Factories\AiConfigVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'version', 'system_prompt', 'voice_style_prompt', 'text_model', 'live_model', 'live_voice', 'temperature',
    'max_output_tokens', 'vad_silence_ms', 'chat_enabled', 'voice_enabled', 'voice_max_seconds', 'created_by', 'notes',
])]
class AiConfigVersion extends Model
{
    /** @use HasFactory<AiConfigVersionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'chat_enabled' => 'boolean',
            'voice_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A version is a record of what the assistant was told, so conversations can point at it later.
     * Only the active flag may change after insert.
     */
    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $changed = array_diff(array_keys($version->getDirty()), ['is_active', 'updated_at']);

            if ($changed !== []) {
                throw new LogicException('AI config versions are immutable; create a new version instead.');
            }
        });
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public static function nextVersionNumber(): int
    {
        return (int) static::query()->max('version') + 1;
    }

    /** Exactly one version is active; switching happens in one transaction. */
    public function activate(): void
    {
        DB::transaction(function (): void {
            static::query()->where('is_active', true)->where('id', '!=', $this->id)->get()
                ->each(fn (self $other) => $other->forceFill(['is_active' => false])->save());

            // is_active is deliberately not mass-assignable, so it is set explicitly.
            $this->forceFill(['is_active' => true])->save();
        });
    }

    /** @param Builder<AiConfigVersion> $query */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('version');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
