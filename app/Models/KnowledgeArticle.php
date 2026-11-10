<?php

namespace App\Models;

use Database\Factories\KnowledgeArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'body', 'is_published', 'always_include'])]
class KnowledgeArticle extends Model
{
    /** @use HasFactory<KnowledgeArticleFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'always_include' => 'boolean',
        ];
    }

    /** @param Builder<KnowledgeArticle> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
