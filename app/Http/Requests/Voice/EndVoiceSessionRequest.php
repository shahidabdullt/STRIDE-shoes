<?php

namespace App\Http\Requests\Voice;

use Illuminate\Foundation\Http\FormRequest;

class EndVoiceSessionRequest extends FormRequest
{
    public const REASONS = ['user_ended', 'go_away_failed', 'max_duration', 'token_expired', 'mic_denied', 'backgrounded', 'error'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'in:'.implode(',', self::REASONS)],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'usage' => ['nullable', 'array'],
            'usage.input_tokens' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'usage.output_tokens' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
