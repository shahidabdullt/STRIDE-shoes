<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size' => fake()->randomElement([7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5, 11]),
            'sku' => 'SKU-'.fake()->unique()->bothify('????-#####'),
            'stock' => fake()->numberBetween(0, 12),
        ];
    }
}
