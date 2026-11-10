<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EvaluationCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rubric' => ['required', 'string', 'max:2000'],
            'scale' => ['required', 'in:pass_fail,score_1_5'],
            'applies_to' => ['required', 'in:all,text,voice'],
            'is_active' => ['required', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, mixed> */
    public function attributesForSave(): array
    {
        return [...$this->validated(), 'sort' => (int) ($this->validated('sort') ?? 0)];
    }
}
