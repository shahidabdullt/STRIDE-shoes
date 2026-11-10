<?php

namespace App\Models;

use Database\Factories\DataPointDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'label', 'instruction', 'type', 'options', 'is_active', 'sort'])]
class DataPointDefinition extends Model
{
    /** @use HasFactory<DataPointDefinitionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean'];
    }

    /** @return HasMany<ConversationDataPoint, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ConversationDataPoint::class);
    }
}
