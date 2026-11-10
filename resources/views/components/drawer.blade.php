@props(['id', 'title'])

{{--
    Below the `lg` breakpoint this is a bottom sheet opened by [data-drawer-open="{id}"] and closed by
    [data-drawer-close], the backdrop or Escape (see resources/js/drawer.js). From `lg` up it is simply
    a static sidebar, so the same markup serves phones and desktops.
--}}
<div
    id="{{ $id }}"
    data-drawer
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    {{ $attributes->class('max-lg:invisible max-lg:fixed max-lg:inset-0 max-lg:z-40 max-lg:data-[open]:visible lg:static') }}
>
    <div data-drawer-close class="absolute inset-0 bg-stone-900/50 lg:hidden"></div>
    <div class="max-lg:absolute max-lg:inset-x-0 max-lg:bottom-0 max-lg:max-h-[85dvh] max-lg:overflow-y-auto max-lg:rounded-t-2xl max-lg:bg-white max-lg:p-4 max-lg:pb-[max(1rem,env(safe-area-inset-bottom))] lg:p-0">
        <div class="mb-3 flex items-center justify-between lg:hidden">
            <h2 id="{{ $id }}-title" class="text-lg font-semibold">{{ $title }}</h2>
            <button type="button" data-drawer-close class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-stone-100" aria-label="Close {{ strtolower($title) }}">
                <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
            </button>
        </div>
        {{ $slot }}
    </div>
</div>
