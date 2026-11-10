@props(['name', 'label', 'value' => null, 'rows' => 4, 'hint' => null])

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-stone-800">{{ $label }}</label>
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->class('block w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-base text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700') }}
    >{{ old($name, $value) }}</textarea>
    @if ($hint)
        <p class="mt-1 text-sm text-stone-600">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
