<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Faq> */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question' => rtrim(fake()->unique()->sentence(7), '.').'?',
            'answer' => fake()->paragraph(),
            'topic' => fake()->randomElement(['shipping', 'returns', 'sizing', 'orders']),
            'is_published' => true,
            'sort' => 0,
        ];
    }
}
