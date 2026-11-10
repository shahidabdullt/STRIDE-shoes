<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fictional Stride catalog: 6 categories, 40 shoes at $59-$189, US half sizes.
 * Deterministic (no randomness) so re-running it updates rather than duplicates.
 * Product photos live in public/images/products/<slug>.jpg; a product without a file keeps the placeholder.
 */
class DemoCatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        'road' => ['Road Running', 'Cushioned and responsive shoes for pavement and tracks.'],
        'trail' => ['Trail Running', 'Grippy, protective shoes for dirt, rock and mud.'],
        'walking' => ['Walking', 'All-day comfort for city streets and long walks.'],
        'casual' => ['Casual Sneakers', 'Easy everyday style with a comfortable fit.'],
        'training' => ['Gym & Training', 'Stable shoes for lifting, classes and cross-training.'],
        'recovery' => ['Recovery & Slides', 'Soft, easy-on shoes for rest days.'],
    ];

    private const SIZE_RANGES = [
        'men' => [7, 13],
        'women' => [6, 11],
        'unisex' => [6, 13],
    ];

    /**
     * [category, name, audience, activity, color, price in dollars, tagline, weight g, drop mm, waterproof, width, featured]
     */
    private const PRODUCTS = [
        ['road', 'Pace 1', 'men', 'running', 'Black', 119, 'Balanced everyday road trainer', 280, 10, false, 'standard', true],
        ['road', 'Pace 1', 'women', 'running', 'Coral', 119, 'Balanced everyday road trainer', 245, 10, false, 'standard', true],
        ['road', 'Pace 1', 'men', 'running', 'Slate Blue', 119, 'Balanced everyday road trainer', 280, 10, false, 'standard', false],
        ['road', 'Velocity', 'men', 'running', 'Volt', 149, 'Light, fast shoe for tempo runs and race day', 230, 6, false, 'standard', true],
        ['road', 'Velocity', 'women', 'running', 'Lilac', 149, 'Light, fast shoe for tempo runs and race day', 205, 6, false, 'standard', false],
        ['road', 'Cruise Max', 'unisex', 'running', 'Grey', 139, 'Maximum cushioning for easy and long miles', 310, 8, false, 'wide', false],
        ['road', 'Tempo Lite', 'men', 'running', 'White', 99, 'Entry-level road shoe at a friendly price', 270, 10, false, 'standard', false],
        ['road', 'Flash 2', 'women', 'running', 'Mint', 129, 'Springy ride for fast days', 225, 8, false, 'standard', false],
        ['road', 'Flash 2', 'men', 'running', 'Navy', 129, 'Springy ride for fast days', 255, 8, false, 'standard', false],
        ['trail', 'Ridge Runner', 'men', 'trail', 'Olive', 139, 'Aggressive grip for rocky singletrack', 300, 6, false, 'standard', false],
        ['trail', 'Ridge Runner', 'women', 'trail', 'Sand', 139, 'Aggressive grip for rocky singletrack', 265, 6, false, 'standard', false],
        ['trail', 'Summit GTX', 'men', 'trail', 'Charcoal', 189, 'Waterproof trail shoe for wet, cold days', 340, 8, true, 'standard', true],
        ['trail', 'Summit GTX', 'women', 'trail', 'Teal', 189, 'Waterproof trail shoe for wet, cold days', 300, 8, true, 'standard', false],
        ['trail', 'Creek Dash', 'unisex', 'trail', 'Orange', 119, 'Quick-drying shoe for muddy, flowing trails', 270, 4, false, 'standard', false],
        ['trail', 'Boulder Pro', 'men', 'trail', 'Brown', 159, 'Rock plate and toe guard for technical terrain', 330, 6, false, 'wide', false],
        ['walking', 'Stroll Comfort', 'women', 'walking', 'Navy', 79, 'Soft, supportive shoe for daily walks', 240, 8, false, 'standard', false],
        ['walking', 'Stroll Comfort', 'men', 'walking', 'Grey', 79, 'Soft, supportive shoe for daily walks', 290, 8, false, 'standard', false],
        ['walking', 'Promenade', 'unisex', 'walking', 'White', 89, 'Clean look with all-day cushioning', 260, 10, false, 'standard', false],
        ['walking', 'City Walker', 'men', 'walking', 'Black', 99, 'Water-resistant upper for commutes', 300, 10, false, 'standard', false],
        ['walking', 'City Walker', 'women', 'walking', 'Taupe', 99, 'Water-resistant upper for commutes', 255, 10, false, 'standard', false],
        ['walking', 'Wide Fit Walker', 'men', 'walking', 'Brown', 109, 'Roomy toe box for wide feet', 320, 8, false, 'wide', false],
        ['walking', 'Easy Stride', 'women', 'walking', 'Rose', 69, 'Lightweight, flexible and easy to slip on', 210, 6, false, 'standard', false],
        ['casual', 'Street Classic', 'unisex', 'casual', 'White', 79, 'Timeless low-top sneaker', 340, 12, false, 'standard', true],
        ['casual', 'Street Classic', 'unisex', 'casual', 'Black', 79, 'Timeless low-top sneaker', 340, 12, false, 'standard', false],
        ['casual', 'Court Low', 'men', 'casual', 'Cream', 89, 'Court-inspired leather look', 360, 12, false, 'standard', false],
        ['casual', 'Court Low', 'women', 'casual', 'Pink', 89, 'Court-inspired leather look', 310, 12, false, 'standard', false],
        ['casual', 'Canvas Daily', 'unisex', 'casual', 'Navy', 59, 'Lightweight canvas for warm days', 290, 8, false, 'standard', false],
        ['casual', 'Canvas Daily', 'unisex', 'casual', 'Red', 59, 'Lightweight canvas for warm days', 290, 8, false, 'standard', false],
        ['casual', 'Retro 86', 'unisex', 'casual', 'Green', 99, 'Throwback runner styling with modern cushioning', 320, 10, false, 'standard', false],
        ['casual', 'Loft High', 'women', 'casual', 'White', 109, 'High-top with a padded collar', 330, 12, false, 'standard', false],
        ['training', 'Gym Flex', 'men', 'training', 'Black', 99, 'Flexible all-rounder for classes and circuits', 290, 6, false, 'standard', false],
        ['training', 'Gym Flex', 'women', 'training', 'Plum', 99, 'Flexible all-rounder for classes and circuits', 250, 6, false, 'standard', false],
        ['training', 'Lift Pro', 'men', 'training', 'Grey', 129, 'Flat, stable base for heavy lifts', 350, 0, false, 'wide', false],
        ['training', 'Lift Pro', 'women', 'training', 'Black', 129, 'Flat, stable base for heavy lifts', 310, 0, false, 'standard', false],
        ['training', 'HIIT Quick', 'unisex', 'training', 'Blue', 109, 'Grippy and responsive for fast intervals', 280, 4, false, 'standard', false],
        ['training', 'Cross Train 3', 'men', 'training', 'Red', 119, 'Durable upper for rope climbs and burpees', 320, 4, false, 'standard', false],
        ['recovery', 'Cloud Slide', 'unisex', 'casual', 'Black', 59, 'Cushioned slide for after workouts', 220, 6, false, 'standard', false],
        ['recovery', 'Cloud Slide', 'unisex', 'casual', 'Sand', 59, 'Cushioned slide for after workouts', 220, 6, false, 'standard', false],
        ['recovery', 'Recover Slip-on', 'women', 'casual', 'Lavender', 89, 'Soft slip-on for tired feet', 230, 8, false, 'standard', false],
        ['recovery', 'Recover Slip-on', 'men', 'casual', 'Grey', 89, 'Soft slip-on for tired feet', 270, 8, false, 'standard', false],
    ];

    private const MATERIALS = [
        'running' => 'Engineered mesh',
        'trail' => 'Ripstop mesh with a protective overlay',
        'walking' => 'Breathable knit',
        'casual' => 'Canvas and synthetic leather',
        'training' => 'Reinforced mesh',
    ];

    public function run(): void
    {
        $categories = [];
        foreach (array_values(self::CATEGORIES) as $sort => [$name, $description]) {
            $categories[Str::slug($name)] = Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'sort' => $sort],
            );
        }

        foreach (self::PRODUCTS as [$categoryKey, $name, $audience, $activity, $color, $dollars, $tagline, $weight, $drop, $waterproof, $width, $featured]) {
            $category = $categories[Str::slug(self::CATEGORIES[$categoryKey][0])];
            $title = $audience === 'unisex' ? $name : $name.' '.ucfirst($audience);

            $product = Product::updateOrCreate(
                ['slug' => Str::slug("$name $audience $color")],
                [
                    'category_id' => $category->id,
                    'name' => $title,
                    'short_description' => $tagline,
                    'description' => $this->describe($tagline, $category->name, $weight, $drop, $waterproof, $width),
                    'price_cents' => $dollars * 100,
                    'audience' => $audience,
                    'activity' => $activity,
                    'color' => $color,
                    'attributes' => [
                        'material' => self::MATERIALS[$activity],
                        'weight_g' => $weight,
                        'drop_mm' => $drop,
                        'waterproof' => $waterproof,
                        'width' => $width,
                    ],
                    'is_active' => true,
                    'is_featured' => $featured,
                ],
            );

            $this->seedVariants($product, $audience);
            $this->seedImage($product);
        }
    }

    private function seedImage(Product $product): void
    {
        $path = "images/products/{$product->slug}.jpg";

        if (! is_file(public_path($path))) {
            return;
        }

        $product->images()->updateOrCreate(
            ['path' => $path],
            ['alt' => "{$product->name} in {$product->color}", 'sort' => 0],
        );
    }

    private function describe(string $tagline, string $category, int $weight, int $drop, bool $waterproof, string $width): string
    {
        return sprintf(
            '%s. Part of our %s range. Weighs about %d g (a typical size) with a %d mm heel-to-toe drop. %s %s fit.',
            $tagline,
            $category,
            $weight,
            $drop,
            $waterproof ? 'Waterproof membrane keeps feet dry.' : 'Not waterproof.',
            ucfirst($width),
        );
    }

    private function seedVariants(Product $product, string $audience): void
    {
        [$min, $max] = self::SIZE_RANGES[$audience];
        $rows = [];

        for ($size = $min; $size <= $max; $size += 0.5) {
            $rows[] = [
                'product_id' => $product->id,
                'size' => $size,
                'sku' => strtoupper(Str::slug($product->slug)).'-'.str_pad((string) ($size * 10), 3, '0', STR_PAD_LEFT),
                // Deterministic stock 0-12 with a few sold-out sizes, so stock questions have real answers.
                'stock' => crc32($product->slug.$size) % 13,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProductVariant::upsert($rows, ['product_id', 'size'], ['sku', 'stock', 'updated_at']);
    }
}
