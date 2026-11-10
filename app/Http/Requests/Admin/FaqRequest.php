<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:3000'],
            'topic' => ['required', 'string', 'max:60'],
            'is_published' => ['required', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'from_gap' => ['nullable', 'integer', 'exists:conversation_questions,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function attributesForSave(): array
    {
        return [...$this->safe()->except('from_gap'), 'sort' => (int) ($this->validated('sort') ?? 0)];
    }
}
