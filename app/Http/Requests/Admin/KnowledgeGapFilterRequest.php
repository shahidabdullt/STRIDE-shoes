<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class KnowledgeGapFilterRequest extends FormRequest
{
    protected $redirectRoute = 'admin.knowledge-gaps.index';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'channel' => ['nullable', 'in:text,voice'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'resolved' => ['nullable', 'in:no,yes'],
        ];
    }
}
