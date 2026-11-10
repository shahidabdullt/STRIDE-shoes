{{--
    Voice assistant (BLUEPRINT §2.5, §2.7). Full-screen sheet on phones, side panel from `sm` up.
    resources/js/voice/voice-panel.js drives it. Nothing starts the microphone until the customer has
    read the notice and pressed Start.
--}}
<div
    data-voice
    data-enabled="{{ $voiceEnabled ? '1' : '0' }}"
    data-default-mobile="{{ config('assistant.voice_default_mode_mobile') }}"
    data-default-desktop="{{ config('assistant.voice_default_mode_desktop') }}"
    data-worklet-url="{{ Vite::asset('resources/js/voice/audio-capture.worklet.js') }}"
>
    <button
        type="button"
        data-voice-launcher
        aria-controls="voice-panel"
        aria-expanded="false"
        aria-label="Talk to Stride (voice assistant)"
        class="fixed right-4 bottom-[calc(9.5rem+env(safe-area-inset-bottom))] z-30 inline-flex size-12 items-center justify-center rounded-full border border-stone-300 bg-white text-emerald-800 shadow-lg hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 md:bottom-24"
    >
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path stroke-linecap="round" d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
    </button>

    <section
        id="voice-panel"
        data-voice-panel
        role="dialog"
        aria-modal="false"
        aria-labelledby="voice-title"
        hidden
        class="fixed inset-x-0 top-0 z-50 flex h-dvh flex-col bg-white sm:inset-auto sm:right-6 sm:bottom-6 sm:h-[min(640px,calc(100dvh-3rem))] sm:w-[380px] sm:rounded-2xl sm:border sm:border-stone-200 sm:shadow-2xl"
    >
        <header class="flex items-center gap-2 border-b border-stone-200 px-4 pt-[max(0.5rem,env(safe-area-inset-top))] pb-2">
            <h2 id="voice-title" class="flex-1 text-base font-semibold">Talk to Stride</h2>
            <span data-voice-timer class="hidden text-sm tabular-nums text-stone-600"></span>
            <button type="button" data-voice-close class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-stone-100" aria-label="Close voice assistant">
                <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
            </button>
        </header>

        {{-- Before a session: notice, mode, start --}}
        <div data-voice-intro class="flex flex-1 flex-col gap-4 overflow-y-auto px-4 py-4">
            <p class="text-sm text-stone-700">
                Ask about shoes, sizes and stock, delivery and returns, or ask Stride to add something to your cart.
            </p>
            <p class="rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600">
                Your voice is sent to Google's Gemini service to answer you. No audio is stored; a written transcript is kept
                for {{ config('assistant.retention_days') }} days to improve the assistant. Don't share payment or sensitive details.
                Voice uses about 2.5 MB of data per minute.
            </p>

            <fieldset>
                <legend class="mb-2 text-sm font-medium">How do you want to talk?</legend>
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-stone-300 p-3 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                        <input type="radio" name="voice-mode" value="hold_to_talk" class="mt-1 size-4 accent-emerald-700">
                        <span><span class="block text-sm font-semibold">Hold to talk</span><span class="block text-xs text-stone-600">Press and hold the button while you speak. Best on a phone speaker.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-stone-300 p-3 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                        <input type="radio" name="voice-mode" value="hands_free" class="mt-1 size-4 accent-emerald-700">
                        <span><span class="block text-sm font-semibold">Hands-free</span><span class="block text-xs text-stone-600">Just talk; you can interrupt. Use headphones on a phone so Stride doesn't hear itself.</span></span>
                    </label>
                </div>
            </fieldset>

            <button type="button" data-voice-start class="mt-auto min-h-12 rounded-lg bg-emerald-700 px-5 text-base font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                Start talking
            </button>
        </div>

        {{-- During a session: captions and controls --}}
        <div data-voice-live hidden class="flex flex-1 flex-col overflow-hidden">
            <p data-voice-status role="status" aria-live="polite" class="px-4 pt-3 text-sm font-medium text-stone-700"></p>
            <div data-voice-captions aria-label="Captions" class="flex-1 space-y-2 overflow-y-auto overscroll-contain px-4 py-3 text-sm"></div>

            <div class="flex flex-col items-center gap-3 border-t border-stone-200 px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <button
                    type="button"
                    data-voice-hold
                    hidden
                    class="flex size-28 touch-none select-none items-center justify-center rounded-full bg-emerald-700 text-center text-sm font-semibold text-white shadow-lg [-webkit-touch-callout:none] data-[active]:scale-95 data-[active]:bg-emerald-900"
                >
                    Hold to talk
                </button>
                <div data-voice-meter hidden class="flex size-28 items-center justify-center rounded-full bg-emerald-50" aria-hidden="true">
                    <span class="size-16 rounded-full bg-emerald-700 transition-transform duration-75" style="transform: scale(calc(0.6 + var(--level, 0)))"></span>
                </div>
                <button type="button" data-voice-end class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-5 text-sm font-semibold text-stone-900 hover:bg-stone-50">
                    End conversation
                </button>
            </div>
        </div>

        {{-- Errors and the end of a session --}}
        <div data-voice-message hidden class="flex flex-1 flex-col justify-center gap-4 px-4 py-6 text-center">
            <p data-voice-message-text role="alert" class="text-sm text-stone-800"></p>
            <button type="button" data-voice-again class="mx-auto min-h-11 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800">Start again</button>
        </div>
    </section>
</div>
