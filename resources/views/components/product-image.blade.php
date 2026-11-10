@props(['product', 'compact' => false])
@php
    $image = $product->images->first();
    // Full width square in cards and galleries; a fixed 5rem thumbnail in lists (compact).
    $size = $compact ? 'size-20 shrink-0 rounded-lg' : 'aspect-square w-full';
@endphp

@if ($image)
    <img
        src="{{ asset($image->path) }}"
        alt="{{ $image->alt }}"
        loading="lazy"
        width="800"
        height="800"
        {{ $attributes->class([$size, 'bg-white object-contain']) }}
    >
@else
    {{-- No photo uploaded yet: a neutral placeholder keeps the layout stable. --}}
    <div {{ $attributes->class([$size, 'flex items-center justify-center bg-stone-100 text-stone-400']) }} role="img" aria-label="{{ $product->name }} in {{ $product->color }} (image coming soon)">
        <svg viewBox="0 0 64 64" class="h-1/3 w-1/3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 40c0-6 4-8 8-9l8-3 6 5 10 2c6 1 12 3 12 8v3H6z"/>
            <path d="M6 46h52"/>
        </svg>
    </div>
@endif
