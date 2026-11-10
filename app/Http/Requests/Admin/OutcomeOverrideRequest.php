<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OutcomeOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> `automatic` removes an override and recomputes. */
    public function rules(): array
    {
        return ['outcome' => ['required', 'in:success,failed,automatic']];
    }
}
