<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'quantity' => ['required', 'integer', 'between:1,'.config('store.max_quantity_per_item')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['variant_id.required' => 'Please choose a size.', 'variant_id.exists' => 'Please choose a valid size.'];
    }
}
