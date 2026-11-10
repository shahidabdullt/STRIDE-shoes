<?php

namespace App\Http\Requests\Admin;

use App\Models\KnowledgeArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class KnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && filled($this->input('title'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('title'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('knowledge_articles', 'slug')->ignore($this->route('article'))],
            'body' => ['required', 'string', 'max:10000'],
            'is_published' => ['required', 'boolean'],
            'always_include' => ['required', 'boolean'],
        ];
    }

    /**
     * Always-included articles go into every prompt, so their combined size is capped.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('always_include') || ! $this->boolean('is_published')) {
                return;
            }

            $limit = (int) config('assistant.always_include_max_words');
            $others = KnowledgeArticle::query()
                ->where('always_include', true)->where('is_published', true)
                ->when($this->route('article'), fn ($query, $article) => $query->whereKeyNot($article->getKey()))
                ->pluck('body')
                ->sum(fn (string $body) => str_word_count($body));
            $total = $others + str_word_count((string) $this->input('body'));

            if ($total > $limit) {
                $validator->errors()->add('body', "Always-included articles may total at most {$limit} words; with this one they would total {$total}. Shorten it or untick \"Always include\".");
            }
        }];
    }
}
