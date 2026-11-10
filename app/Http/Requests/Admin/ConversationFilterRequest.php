<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Conversation list filters (AC-A5): fixed allowed values only; anything else redirects back with errors. */
class ConversationFilterRequest extends FormRequest
{
    protected $redirectRoute = 'admin.conversations.index';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'outcome' => ['nullable', 'in:success,failed,pending'],
            'failure_kind' => ['nullable', 'in:knowledge_gap,technical'],
            'channel' => ['nullable', 'in:text,voice'],
            'device_class' => ['nullable', 'in:mobile,desktop'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
