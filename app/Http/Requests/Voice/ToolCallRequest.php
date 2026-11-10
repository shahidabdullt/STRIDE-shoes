<?php

namespace App\Http\Requests\Voice;

use App\Assistant\ToolRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A Live toolCall forwarded by the browser: untrusted, so the name must be a registered tool. */
class ToolCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'call_id' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', Rule::in(app(ToolRegistry::class)->names())],
            'args' => ['nullable', 'array'],
        ];
    }
}
