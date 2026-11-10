@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh flex-col bg-stone-50 text-stone-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">
        Skip to content
    </a>

    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">{{ config('app.name') }}</a>
            <nav class="flex items-center gap-1 text-sm font-medium" aria-label="Main">
                <a href="{{ route('products.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 hover:bg-stone-100">Shoes</a>
                <a href="{{ route('faqs.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 hover:bg-stone-100">Help</a>
                <a href="{{ route('cart.show') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 hover:bg-stone-100">
                    Cart
                    <span data-cart-count class="min-w-6 justify-center rounded-full bg-emerald-700 px-1.5 text-xs font-semibold text-white {{ ($cartCount ?? 0) > 0 ? 'inline-flex' : 'hidden' }}">{{ $cartCount ?? 0 }}</span>
                </a>
                @can('access-admin')
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 hover:bg-stone-100">Admin</a>
                    {{-- On phones Sign out lives at the end of the admin sub-nav instead (no room here). --}}
                    <form method="POST" action="{{ route('admin.logout') }}" class="max-sm:hidden">
                        @csrf
                        <button type="submit" class="min-h-11 rounded-lg px-3 hover:bg-stone-100">Sign out</button>
                    </form>
                @endcan
            </nav>
        </div>
    </header>

    <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <x-flash />
        <div data-cart-message role="status" aria-live="polite" class="mb-6 hidden rounded-lg border px-4 py-3 text-sm"></div>
        {{ $slot }}
    </main>

    @unless (request()->routeIs('admin.*'))
        <x-chat-widget />
        <x-voice-panel />
    @endunless

    <footer class="border-t border-stone-200 bg-white">
        <p class="mx-auto w-full max-w-6xl px-4 py-4 text-sm text-stone-600">
            Demo store. Payments are simulated and nothing is ever charged.
        </p>
    </footer>
</body>
</html>
