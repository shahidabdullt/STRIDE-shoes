<?php

namespace App\Http\Requests\Voice;

use Illuminate\Foundation\Http\FormRequest;

/** Live transcripts as reported by the browser. Stored as client-reported and never fed back into prompts. */
class TranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'segments' => ['required', 'array', 'min:1', 'max:50'],
            'segments.*.role' => ['required', 'in:user,assistant'],
            'segments.*.text' => ['required', 'string', 'max:2000'],
            'segments.*.at' => ['nullable', 'date'],
        ];
    }
}
