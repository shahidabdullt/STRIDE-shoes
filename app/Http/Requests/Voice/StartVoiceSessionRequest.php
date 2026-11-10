<?php

namespace App\Http\Requests\Voice;

use Illuminate\Foundation\Http\FormRequest;

class StartVoiceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:hands_free,hold_to_talk'],
            'device_class' => ['required', 'in:mobile,desktop'],
        ];
    }
}
