<?php

namespace App\Http\Requests\Admin;

use App\Models\DataPointDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DataPointDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $definition = $this->route('data_point');

        return [
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,59}$/', Rule::unique('data_point_definitions', 'key')->ignore($definition)],
            'label' => ['required', 'string', 'max:255'],
            'instruction' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'in:string,number,boolean,enum'],
            'options' => ['nullable', 'required_if:type,enum', 'string', 'max:1500'],
            'is_active' => ['required', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['key.regex' => 'Use lower-case letters, digits and underscores, starting with a letter (e.g. shoe_size).'];
    }

    /** Options arrive as comma-separated text; changing the type of a definition with values is blocked. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $options = $this->optionList();
            if ($this->input('type') === 'enum' && (count($options) < 2 || count($options) > 20 || collect($options)->contains(fn ($o) => mb_strlen($o) > 60))) {
                $validator->errors()->add('options', 'Give 2 to 20 comma-separated options of up to 60 characters.');
            }

            /** @var DataPointDefinition|null $definition */
            $definition = $this->route('data_point');
            if ($definition?->exists && $definition->type !== $this->input('type') && $definition->values()->exists()) {
                $validator->errors()->add('type', 'This data point already has values. Create a new data point instead of changing its type.');
            }
        }];
    }

    /** @return list<string> */
    public function optionList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->input('options')))));
    }

    /** @return array<string, mixed> */
    public function attributesForSave(): array
    {
        return [
            ...$this->safe()->except('options'),
            'options' => $this->validated('type') === 'enum' ? $this->optionList() : null,
            'sort' => (int) ($this->validated('sort') ?? 0),
        ];
    }
}
