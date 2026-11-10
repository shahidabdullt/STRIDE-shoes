<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    /** Invalid filters send the visitor back to the unfiltered list with the errors shown. */
    protected $redirectRoute = 'products.index';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100', Rule::exists('categories', 'slug')],
            'activity' => ['nullable', Rule::in(['running', 'trail', 'walking', 'casual', 'training'])],
            'audience' => ['nullable', Rule::in(['men', 'women', 'unisex'])],
            'size' => ['nullable', 'numeric', 'between:3,15', fn ($attribute, $value, $fail) => fmod((float) $value * 2, 1.0) === 0.0 || $fail('The size must be a whole or half size.')],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:1000', 'gte:min_price'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
