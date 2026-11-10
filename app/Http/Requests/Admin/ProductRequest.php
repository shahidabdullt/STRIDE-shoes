<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public const AUDIENCES = ['men', 'women', 'unisex'];

    public const ACTIVITIES = ['running', 'trail', 'walking', 'casual', 'training'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && filled($this->input('name'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('name').' '.$this->input('color'))]);
        }

        // The form always renders a few empty rows for new sizes; rows left blank are not variants.
        $rows = collect($this->input('variants', []))
            ->filter(fn ($row) => is_array($row) && (filled($row['size'] ?? null) || filled($row['sku'] ?? null) || filled($row['id'] ?? null)))
            ->values()
            ->all();

        $this->merge(['variants' => $rows]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'between:1,1000', 'decimal:0,2'],
            'audience' => ['required', Rule::in(self::AUDIENCES)],
            'activity' => ['required', Rule::in(self::ACTIVITIES)],
            'color' => ['required', 'string', 'max:40'],
            'material' => ['nullable', 'string', 'max:100'],
            'weight_g' => ['nullable', 'integer', 'between:0,2000'],
            'drop_mm' => ['nullable', 'integer', 'between:0,30'],
            'width' => ['nullable', 'string', 'max:20'],
            'waterproof' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.size' => ['required', 'numeric', 'between:3,15'],
            'variants.*.sku' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.stock' => ['required', 'integer', 'between:0,10000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Product|null $product */
            $product = $this->route('product');
            $rows = $this->input('variants');
            $ownIds = $product ? $product->variants()->pluck('id')->all() : [];
            $sizes = [];
            $skus = [];

            foreach ($rows as $index => $row) {
                $size = (float) $row['size'];
                $sku = strtolower((string) $row['sku']);

                if (fmod($size * 10, 5) !== 0.0) {
                    $validator->errors()->add("variants.$index.size", 'Sizes go in half steps, for example 9 or 9.5.');
                }

                if (isset($sizes[(string) $size])) {
                    $validator->errors()->add("variants.$index.size", 'Each size can appear only once.');
                }

                if (isset($skus[$sku])) {
                    $validator->errors()->add("variants.$index.sku", 'Each SKU can appear only once.');
                }

                if (filled($row['id'] ?? null) && ! in_array((int) $row['id'], $ownIds, true)) {
                    $validator->errors()->add("variants.$index.id", 'That size does not belong to this product.');
                }

                $sizes[(string) $size] = true;
                $skus[$sku] = true;
            }

            $keptIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $taken = ProductVariant::query()
                ->whereIn('sku', collect($rows)->pluck('sku')->all())
                ->whereNotIn('id', $keptIds)
                ->pluck('sku')->map(fn ($sku) => strtolower($sku))->all();

            foreach ($rows as $index => $row) {
                if (in_array(strtolower((string) $row['sku']), $taken, true)) {
                    $validator->errors()->add("variants.$index.sku", 'This SKU is already used by another size.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function productData(): array
    {
        return [
            'category_id' => (int) $this->validated('category_id'),
            'name' => $this->validated('name'),
            'slug' => $this->validated('slug'),
            'short_description' => $this->validated('short_description'),
            'description' => $this->validated('description'),
            'price_cents' => (int) round(((float) $this->validated('price')) * 100),
            'audience' => $this->validated('audience'),
            'activity' => $this->validated('activity'),
            'color' => $this->validated('color'),
            'attributes' => array_filter([
                'material' => $this->validated('material'),
                'weight_g' => $this->validated('weight_g') !== null ? (int) $this->validated('weight_g') : null,
                'drop_mm' => $this->validated('drop_mm') !== null ? (int) $this->validated('drop_mm') : null,
                'waterproof' => $this->boolean('waterproof'),
                'width' => $this->validated('width'),
            ], fn ($value) => $value !== null && $value !== ''),
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
        ];
    }

    /** @return array<int, array{id: int|null, size: float, sku: string, stock: int}> */
    public function variantRows(): array
    {
        return collect($this->validated('variants'))->map(fn (array $row) => [
            'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
            'size' => (float) $row['size'],
            'sku' => $row['sku'],
            'stock' => (int) $row['stock'],
        ])->all();
    }
}
