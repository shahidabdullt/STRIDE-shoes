{{--
    Text assistant. The launcher sits above the product page's fixed "Add to cart" bar on phones.
    Below `sm` the panel is a full-screen sheet; from `sm` up a 380 px panel. resources/js/chat/widget.js
    drives it; all model text is inserted with textContent, never as HTML.
--}}
<div data-chat data-enabled="{{ $chatEnabled ? '1' : '0' }}" data-greeting="{{ config('assistant.greeting') }}" data-help-url="{{ route('faqs.index') }}">
    <button
        type="button"
        data-chat-launcher
        aria-controls="chat-panel"
        aria-expanded="false"
        class="fixed right-4 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-30 inline-flex min-h-12 items-center gap-2 rounded-full bg-emerald-700 px-5 text-sm font-semibold text-white shadow-lg hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 md:bottom-6"
    >
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6 3-3h11a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 1 1.7"/></svg>
        Ask Stride
    </button>

    <section
        id="chat-panel"
        data-chat-panel
        role="dialog"
        aria-modal="false"
        aria-labelledby="chat-title"
        hidden
        class="fixed inset-x-0 top-0 z-50 flex h-dvh flex-col bg-white sm:inset-auto sm:right-6 sm:bottom-6 sm:h-[min(640px,calc(100dvh-3rem))] sm:w-[380px] sm:rounded-2xl sm:border sm:border-stone-200 sm:shadow-2xl"
    >
        <header class="flex items-center gap-2 border-b border-stone-200 px-4 pt-[max(0.5rem,env(safe-area-inset-top))] pb-2">
            <h2 id="chat-title" class="flex-1 text-base font-semibold">Stride assistant</h2>
            <button type="button" data-chat-new class="min-h-11 rounded-lg px-3 text-sm font-medium text-stone-700 hover:bg-stone-100">New chat</button>
            <button type="button" data-chat-close class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-stone-100" aria-label="Close chat">
                <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
            </button>
        </header>

        <p class="border-b border-stone-100 bg-stone-50 px-4 py-2 text-xs text-stone-600">
            Conversations are stored for {{ config('assistant.retention_days') }} days to improve the assistant. Don't share payment or sensitive details.
        </p>

        <div data-chat-log role="log" aria-live="polite" aria-label="Conversation" class="flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-4"></div>

        <p data-chat-status aria-live="polite" class="hidden px-4 pb-1 text-sm text-stone-600"></p>

        <form data-chat-access hidden class="space-y-2 border-t border-stone-200 px-4 py-3">
            <label for="chat-access-code" class="block text-sm font-medium">Access code</label>
            <div class="flex gap-2">
                <input id="chat-access-code" name="code" type="password" maxlength="64" autocomplete="off" required class="block min-h-11 w-full min-w-0 rounded-lg border border-stone-300 px-3 text-base focus-visible:outline-2 focus-visible:outline-emerald-700">
                <button type="submit" class="min-h-11 shrink-0 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800">Unlock</button>
            </div>
        </form>

        <form data-chat-form class="flex items-end gap-2 border-t border-stone-200 px-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <label for="chat-input" class="sr-only">Message</label>
            <textarea
                id="chat-input"
                name="message"
                rows="1"
                maxlength="{{ config('assistant.message_max_chars') }}"
                placeholder="Ask about shoes, sizes or delivery"
                enterkeyhint="send"
                class="block max-h-32 min-h-11 w-full min-w-0 resize-none rounded-lg border border-stone-300 px-3 py-2.5 text-base focus-visible:outline-2 focus-visible:outline-emerald-700"
            ></textarea>
            <button type="submit" class="min-h-11 shrink-0 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">Send</button>
        </form>
    </section>
</div>
