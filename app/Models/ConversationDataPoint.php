<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'data_point_definition_id', 'value_text', 'value_number', 'value_bool', 'evidence'])]
class ConversationDataPoint extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value_number' => 'decimal:2', 'value_bool' => 'boolean'];
    }

    /** @return BelongsTo<DataPointDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(DataPointDefinition::class, 'data_point_definition_id');
    }

    /** The stored value as text for display, whatever its type. */
    public function displayValue(): string
    {
        return match (true) {
            $this->value_bool !== null => $this->value_bool ? 'Yes' : 'No',
            $this->value_number !== null => rtrim(rtrim((string) $this->value_number, '0'), '.'),
            default => (string) $this->value_text,
        };
    }
}
