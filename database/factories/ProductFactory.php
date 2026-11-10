<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'short_description' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'price_cents' => fake()->numberBetween(59, 189) * 100,
            'audience' => fake()->randomElement(['men', 'women', 'unisex']),
            'activity' => fake()->randomElement(['running', 'trail', 'walking', 'casual', 'training']),
            'color' => fake()->safeColorName(),
            'attributes' => ['material' => 'Engineered mesh', 'weight_g' => 280, 'drop_mm' => 8, 'waterproof' => false, 'width' => 'standard'],
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
