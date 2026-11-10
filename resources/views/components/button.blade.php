@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])
@php
    $classes = 'inline-flex min-h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 '.match ($variant) {
        'secondary' => 'border border-stone-300 bg-white text-stone-900 hover:bg-stone-50',
        default => 'bg-emerald-700 text-white hover:bg-emerald-800',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
