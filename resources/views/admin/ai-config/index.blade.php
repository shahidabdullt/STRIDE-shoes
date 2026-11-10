<x-layouts.app title="AI config">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">AI configuration</h1>

    <p class="mb-4 max-w-3xl text-sm text-stone-700">
        Each save creates a new, unchangeable version so every conversation can be traced to the exact settings it used.
        Only one version is active. The assistant itself arrives in a later milestone: these settings are stored but not used yet.
    </p>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="text-lg font-semibold">
                New version
                @if ($active)<span class="text-sm font-normal text-stone-600">(starts from version {{ $active->version }})</span>@endif
            </h2>
            <form method="POST" action="{{ route('admin.ai-config.store') }}" class="mt-4 space-y-4">
                @csrf
                <x-textarea name="system_prompt" label="System prompt" :value="$draft->system_prompt" rows="10" required />
                <x-textarea name="voice_style_prompt" label="Extra voice-only guidance" :value="$draft->voice_style_prompt" rows="3" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="text_model" label="Text model" :options="array_combine(config('assistant.text_models'), config('assistant.text_models'))" :value="$draft->text_model" />
                    <x-select name="live_model" label="Voice (Live) model" :options="array_combine(config('assistant.live_models'), config('assistant.live_models'))" :value="$draft->live_model" />
                    <x-input name="live_voice" label="Voice name (optional)" :value="$draft->live_voice" />
                    <x-input name="temperature" label="Temperature (0 to 2)" type="number" step="0.05" min="0" max="2" :value="$draft->temperature" required />
                    <x-input name="max_output_tokens" label="Max output tokens" type="number" min="64" max="8192" :value="$draft->max_output_tokens" required />
                    <x-input name="vad_silence_ms" label="Silence before the voice reply (ms, 500 to 1500)" type="number" min="500" max="1500" :value="$draft->vad_silence_ms" required />
                    <x-input name="voice_max_seconds" label="Max voice session (seconds, 30 to 900)" type="number" min="30" max="900" :value="$draft->voice_max_seconds" required />
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <x-checkbox name="chat_enabled" label="Text chat enabled" :checked="$draft->chat_enabled" />
                    <x-checkbox name="voice_enabled" label="Voice enabled" :checked="$draft->voice_enabled" />
                </div>
                <x-input name="notes" label="What changed? (optional)" :value="''" />
                <x-checkbox name="activate" label="Activate this version now" :checked="true" />
                <x-button type="submit">Save new version</x-button>
            </form>
        </x-card>

        <div>
            <h2 class="mb-2 text-lg font-semibold">History</h2>
            <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
                @forelse ($versions as $version)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                Version {{ $version->version }}
                                @if ($version->is_active)<x-badge variant="success" class="ml-1">Active</x-badge>@endif
                            </p>
                            <p class="text-sm text-stone-600">
                                {{ $version->text_model }} &middot; {{ $version->created_at->format('j M Y, H:i') }}
                                @unless ($version->chat_enabled) &middot; chat off @endunless
                                @unless ($version->voice_enabled) &middot; voice off @endunless
                            </p>
                            @if ($version->notes)<p class="mt-1 text-sm text-stone-700">{{ $version->notes }}</p>@endif
                        </div>
                        @unless ($version->is_active)
                            <form method="POST" action="{{ route('admin.ai-config.activate', $version) }}" data-confirm="Make version {{ $version->version }} the active version?">
                                @csrf
                                <x-button variant="secondary" type="submit">Activate</x-button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="p-4 text-stone-600">No versions yet.</li>
                @endforelse
            </ul>
            <div class="mt-4">{{ $versions->links() }}</div>
        </div>
    </div>
</x-layouts.app>
