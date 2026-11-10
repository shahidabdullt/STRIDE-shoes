@props(['name', 'label', 'checked' => false])

<div>
    {{-- The hidden 0 makes an unticked box arrive as false instead of missing. --}}
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-stone-800">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="1"
            @checked((bool) old($name, $checked))
            {{ $attributes->class('size-5 rounded border-stone-300 text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700') }}
        >
        {{ $label }}
    </label>
    @error($name)
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
