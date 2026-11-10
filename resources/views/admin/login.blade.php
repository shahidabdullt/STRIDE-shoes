<x-layouts.app title="Admin sign in">
    <div class="mx-auto max-w-sm">
        <h1 class="mb-4 text-2xl font-bold tracking-tight">Admin sign in</h1>

        <x-card>
            <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
                @csrf
                <x-input name="email" label="Email" type="email" autocomplete="username" required autofocus />
                <x-input name="password" label="Password" type="password" autocomplete="current-password" required />
                <x-button type="submit" class="w-full">Sign in</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
