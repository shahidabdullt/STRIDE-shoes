<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_work(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 9]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 8.5]);
        ProductImage::factory()->create(['product_id' => $product->id]);

        $category = Category::firstOrFail();

        $this->assertTrue($product->category->is($category));
        $this->assertSame(['8.5', '9.0'], $product->variants->pluck('size')->all());
        $this->assertCount(1, $product->images);
        $this->assertCount(1, $category->products);
    }

    public function test_active_scope_excludes_inactive_products(): void
    {
        Product::factory()->create();
        Product::factory()->inactive()->create();

        $this->assertSame(1, Product::active()->count());
    }

    public function test_a_variant_size_is_unique_per_product(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 9]);

        $this->expectException(QueryException::class);
        ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 9]);
    }

    public function test_deleting_a_product_removes_variants_and_images(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductImage::factory()->create(['product_id' => $product->id]);

        $product->delete();

        $this->assertSame(0, ProductVariant::count());
        $this->assertSame(0, ProductImage::count());
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);
        $product->category->delete();
    }

    public function test_product_attributes_are_cast_to_an_array(): void
    {
        $product = Product::factory()->create(['attributes' => ['waterproof' => true]]);

        $this->assertTrue($product->fresh()->attributes['waterproof']);
    }

    public function test_published_scopes_hide_drafts(): void
    {
        Faq::factory()->create();
        Faq::factory()->create(['is_published' => false]);
        KnowledgeArticle::factory()->create();
        KnowledgeArticle::factory()->create(['is_published' => false]);

        $this->assertSame(1, Faq::published()->count());
        $this->assertSame(1, KnowledgeArticle::published()->count());
    }
}
