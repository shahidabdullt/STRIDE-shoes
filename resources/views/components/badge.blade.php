@props(['variant' => 'neutral'])
@php
    $classes = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold '.match ($variant) {
        'success' => 'bg-emerald-100 text-emerald-900',
        'warning' => 'bg-amber-100 text-amber-900',
        'danger' => 'bg-red-100 text-red-900',
        default => 'bg-stone-200 text-stone-800',
    };
@endphp

<span {{ $attributes->class($classes) }}>{{ $slot }}</span>
