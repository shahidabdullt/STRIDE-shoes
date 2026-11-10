<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_a_category_is_created_with_a_generated_slug(): void
    {
        $this->post('/admin/categories', ['name' => 'Kids Shoes', 'sort' => 3])->assertRedirect('/admin/categories');

        $this->assertDatabaseHas('categories', ['name' => 'Kids Shoes', 'slug' => 'kids-shoes', 'sort' => 3]);
    }

    public function test_category_validation_and_unique_slug(): void
    {
        Category::factory()->create(['slug' => 'taken']);

        $this->post('/admin/categories', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/admin/categories', ['name' => 'X', 'slug' => 'taken'])->assertSessionHasErrors('slug');
        $this->post('/admin/categories', ['name' => 'X', 'slug' => 'bad slug!'])->assertSessionHasErrors('slug');
    }

    public function test_a_category_can_keep_its_own_slug_when_edited(): void
    {
        $category = Category::factory()->create(['slug' => 'road']);

        $this->put("/admin/categories/{$category->id}", ['name' => 'Road Shoes', 'slug' => 'road'])->assertSessionHasNoErrors();

        $this->assertSame('Road Shoes', $category->fresh()->name);
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->delete("/admin/categories/{$category->id}")->assertSessionHas('error');
        $this->assertModelExists($category);

        $empty = Category::factory()->create();
        $this->delete("/admin/categories/{$empty->id}")->assertRedirect('/admin/categories');
        $this->assertModelMissing($empty);
    }

    public function test_faq_crud_and_validation(): void
    {
        $this->post('/admin/faqs', ['question' => 'How long is shipping?', 'answer' => '3 days.', 'topic' => 'shipping', 'is_published' => '1'])
            ->assertRedirect('/admin/faqs');
        $faq = Faq::firstOrFail();
        $this->assertTrue($faq->is_published);

        $this->put("/admin/faqs/{$faq->id}", ['question' => $faq->question, 'answer' => '2 days.', 'topic' => 'shipping', 'is_published' => '0'])
            ->assertRedirect('/admin/faqs');
        $this->assertFalse($faq->fresh()->is_published);
        $this->assertSame('2 days.', $faq->fresh()->answer);

        $this->post('/admin/faqs', ['question' => '', 'answer' => '', 'topic' => '', 'is_published' => 'maybe'])
            ->assertSessionHasErrors(['question', 'answer', 'topic', 'is_published']);

        $this->delete("/admin/faqs/{$faq->id}")->assertRedirect('/admin/faqs');
        $this->assertModelMissing($faq);
    }

    public function test_knowledge_article_crud(): void
    {
        $this->post('/admin/knowledge', ['title' => 'Care guide', 'body' => 'Wash gently.', 'is_published' => '1', 'always_include' => '0'])
            ->assertRedirect('/admin/knowledge');

        $this->assertDatabaseHas('knowledge_articles', ['slug' => 'care-guide', 'always_include' => false]);
    }

    public function test_always_included_knowledge_is_capped_across_articles(): void
    {
        config(['assistant.always_include_max_words' => 20]);
        KnowledgeArticle::factory()->create(['always_include' => true, 'body' => implode(' ', array_fill(0, 15, 'word'))]);
        $payload = ['title' => 'Extra', 'is_published' => '1', 'always_include' => '1'];

        $this->post('/admin/knowledge', $payload + ['body' => implode(' ', array_fill(0, 10, 'word'))])
            ->assertSessionHasErrors('body');
        $this->assertDatabaseMissing('knowledge_articles', ['title' => 'Extra']);

        $this->post('/admin/knowledge', $payload + ['body' => implode(' ', array_fill(0, 5, 'word'))])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('knowledge_articles', ['title' => 'Extra']);

        // Not always-included, so the cap does not apply however long it is.
        $this->post('/admin/knowledge', ['title' => 'Long', 'is_published' => '1', 'always_include' => '0', 'body' => implode(' ', array_fill(0, 100, 'word'))])
            ->assertSessionHasNoErrors();
    }

    public function test_editing_an_always_included_article_does_not_count_itself_twice(): void
    {
        config(['assistant.always_include_max_words' => 20]);
        $article = KnowledgeArticle::factory()->create(['always_include' => true, 'body' => implode(' ', array_fill(0, 15, 'word'))]);

        $this->put("/admin/knowledge/{$article->id}", [
            'title' => $article->title, 'slug' => $article->slug, 'is_published' => '1', 'always_include' => '1',
            'body' => implode(' ', array_fill(0, 18, 'word')),
        ])->assertSessionHasNoErrors();
    }
}
