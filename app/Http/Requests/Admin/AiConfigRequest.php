<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'system_prompt' => ['required', 'string', 'max:8000'],
            'voice_style_prompt' => ['nullable', 'string', 'max:2000'],
            'text_model' => ['required', Rule::in(config('assistant.text_models'))],
            'live_model' => ['required', Rule::in(config('assistant.live_models'))],
            'live_voice' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'max_output_tokens' => ['required', 'integer', 'between:64,8192'],
            'vad_silence_ms' => ['required', 'integer', 'between:500,1500'],
            'chat_enabled' => ['required', 'boolean'],
            'voice_enabled' => ['required', 'boolean'],
            // Ephemeral tokens live at most 15 minutes (BLUEPRINT §5.2), so a session cannot be longer.
            'voice_max_seconds' => ['required', 'integer', 'between:30,900'],
            'notes' => ['nullable', 'string', 'max:500'],
            'activate' => ['sometimes', 'boolean'],
        ];
    }
}
