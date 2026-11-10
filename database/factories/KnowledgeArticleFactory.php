<?php

namespace Database\Factories;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<KnowledgeArticle> */
class KnowledgeArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'body' => fake()->paragraphs(2, true),
            'is_published' => true,
            'always_include' => false,
        ];
    }
}
