// The text chat widget (markup: resources/views/components/chat-widget.blade.php).
// Model output is plain text: everything is inserted with textContent, never innerHTML.

import { jsonHeaders } from '../lib/csrf.js';
import { updateCartBadge } from '../cart.js';
import { createChatApi, errorMessage } from './api.js';
import { safeStorage } from './storage.js';

const UUID_KEY = 'stride.chat.uuid';
const OPEN_KEY = 'stride.chat.open';

export function initChat(doc = document, win = window) {
    const root = doc.querySelector('[data-chat]');
    if (!root) return;

    const $ = (selector) => root.querySelector(selector);
    const launcher = $('[data-chat-launcher]');
    const panel = $('[data-chat-panel]');
    const log = $('[data-chat-log]');
    const status = $('[data-chat-status]');
    const form = $('[data-chat-form]');
    const input = form.querySelector('textarea');
    const sendButton = form.querySelector('[type="submit"]');
    const accessForm = $('[data-chat-access]');

    const api = createChatApi({ headers: () => jsonHeaders(doc) });
    const local = safeStorage(() => win.localStorage);
    const session = safeStorage(() => win.sessionStorage);
    const enabled = root.dataset.enabled === '1';

    let uuid = local.get(UUID_KEY);
    let loaded = false;
    let busy = false;
    let pendingMessage = null;

    // ---- rendering ----------------------------------------------------------------------------

    function bubble(role, text, { error = false } = {}) {
        const row = doc.createElement('div');
        row.className = role === 'user' ? 'flex justify-end' : 'flex justify-start';
        const body = doc.createElement('p');
        body.className = 'max-w-[85%] whitespace-pre-wrap break-words rounded-2xl px-3.5 py-2 text-sm ' + (
            role === 'user'
                ? 'bg-emerald-700 text-white'
                : error ? 'border border-amber-200 bg-amber-50 text-amber-950' : 'bg-stone-100 text-stone-900'
        );
        body.textContent = text;
        row.append(body);
        log.append(row);
        scrollToEnd();
    }

    function notice(text) {
        const p = doc.createElement('p');
        p.className = 'rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-center text-xs text-stone-700';
        p.setAttribute('role', 'status');
        p.textContent = text;
        log.append(p);
        scrollToEnd();
    }

    function productCards(products) {
        if (!Array.isArray(products) || products.length === 0) return;
        const list = doc.createElement('ul');
        list.className = '-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1';
        list.setAttribute('aria-label', 'Suggested shoes');

        for (const product of products) {
            if (!safeUrl(product.url)) continue;
            const item = doc.createElement('li');
            item.className = 'w-36 shrink-0 snap-start';
            const link = doc.createElement('a');
            link.href = product.url;
            link.className = 'block overflow-hidden rounded-xl border border-stone-200 bg-white hover:border-emerald-700 focus-visible:outline-2 focus-visible:outline-emerald-700';

            if (product.image && safeUrl(product.image)) {
                const img = doc.createElement('img');
                img.src = product.image;
                img.alt = '';
                img.loading = 'lazy';
                img.width = 144;
                img.height = 144;
                img.className = 'aspect-square w-full bg-white object-contain';
                link.append(img);
            } else {
                // Same square as a photo, so cards line up whether or not a product has an image yet.
                const placeholder = doc.createElement('span');
                placeholder.className = 'block aspect-square w-full bg-stone-100';
                link.append(placeholder);
            }

            const name = doc.createElement('span');
            name.className = 'block px-2 pt-1.5 text-xs font-semibold';
            name.textContent = product.name;
            const price = doc.createElement('span');
            price.className = 'block px-2 pb-2 text-xs text-stone-700';
            price.textContent = product.price;
            link.append(name, price);
            item.append(link);
            list.append(item);
        }

        log.append(list);
        scrollToEnd();
    }

    // Only same-origin links from our own server are rendered as product cards.
    function safeUrl(url) {
        try {
            return new URL(url, win.location.href).origin === win.location.origin;
        } catch {
            return false;
        }
    }

    function scrollToEnd() {
        log.scrollTop = log.scrollHeight;
    }

    function setStatus(text) {
        status.textContent = text ?? '';
        status.classList.toggle('hidden', !text);
    }

    function setBusy(value) {
        busy = value;
        sendButton.disabled = value || !enabled;
        setStatus(value ? 'Thinking…' : null);
    }

    function resetLog() {
        log.replaceChildren();
        bubble('assistant', root.dataset.greeting);
    }

    function disable(text) {
        input.disabled = true;
        sendButton.disabled = true;
        notice(text);
    }

    // ---- open / close ------------------------------------------------------------------------

    async function open({ focus = true } = {}) {
        panel.hidden = false;
        launcher.setAttribute('aria-expanded', 'true');
        launcher.hidden = true;
        session.set(OPEN_KEY, '1');
        fitToViewport();

        if (!loaded) {
            loaded = true;
            await restore();
        }

        if (focus) input.focus();
    }

    function close() {
        panel.hidden = true;
        launcher.hidden = false;
        launcher.setAttribute('aria-expanded', 'false');
        session.remove(OPEN_KEY);
        launcher.focus();
    }

    async function restore() {
        resetLog();

        if (!enabled) {
            disable('The assistant is not available right now. You can still browse and check out as usual.');
            return;
        }

        if (!uuid) return;

        const res = await api.load(uuid);
        if (res.kind === 'ok' && res.data.status === 'active') {
            for (const message of res.data.messages) bubble(message.role, message.content);
        } else if (res.kind === 'access_required') {
            showAccess();
        } else {
            forget();
        }
    }

    function forget() {
        uuid = null;
        local.remove(UUID_KEY);
    }

    // On phones the on-screen keyboard shrinks the visual viewport but not always the layout viewport
    // (iOS Safari): size the sheet to what is actually visible so the input stays above the keyboard.
    function fitToViewport() {
        const vv = win.visualViewport;
        const isSheet = win.matchMedia('(max-width: 639px)').matches;
        panel.style.height = isSheet && vv ? `${vv.height}px` : '';
        panel.style.top = isSheet && vv ? `${vv.offsetTop}px` : '';
    }

    // ---- sending -----------------------------------------------------------------------------

    async function ensureConversation() {
        if (uuid) return true;
        const deviceClass = win.matchMedia('(max-width: 639px)').matches ? 'mobile' : 'desktop';
        const res = await api.start(deviceClass);

        if (res.kind === 'ok') {
            uuid = res.data.uuid;
            local.set(UUID_KEY, uuid);
            return true;
        }

        handleFailure(res);
        return false;
    }

    async function send(text, { retried = false } = {}) {
        setBusy(true);

        try {
            if (!(await ensureConversation())) return;

            const res = await api.send(uuid, text);

            if (res.kind === 'ok') {
                bubble('assistant', res.data.reply, { error: Boolean(res.data.error) });
                productCards(res.data.products);
                if (res.data.cart) updateCartBadge(doc, res.data.cart.count);
                return;
            }

            // The chat expired, ended or belongs to an old cookie: start a fresh one and send once more.
            if ((res.kind === 'not_found' || res.kind === 'ended') && !retried) {
                forget();
                await send(text, { retried: true });
                return;
            }

            if (res.kind === 'access_required') pendingMessage = text;
            handleFailure(res);
        } finally {
            if (!retried) setBusy(false);
        }
    }

    function handleFailure(res) {
        const message = errorMessage(res.data);

        switch (res.kind) {
            case 'access_required':
                showAccess();
                break;
            case 'unavailable':
                disable(message || 'The assistant is not available right now.');
                break;
            case 'rate_limited':
            case 'invalid':
                notice(message || 'Please try again.');
                break;
            case 'expired':
                notice('Your session has expired. Reload the page to keep chatting.');
                break;
            case 'network':
                notice('Could not reach the shop. Check your connection and try again.');
                break;
            default:
                notice('Something went wrong. Please try again.');
        }
    }

    function showAccess() {
        accessForm.hidden = false;
        form.hidden = true;
        accessForm.querySelector('input').focus();
    }

    // ---- events ------------------------------------------------------------------------------

    launcher.addEventListener('click', () => open());
    $('[data-chat-close]').addEventListener('click', close);

    $('[data-chat-new]').addEventListener('click', () => {
        if (uuid) api.end(uuid);
        forget();
        resetLog();
        if (!enabled) disable('The assistant is not available right now.');
        input.focus();
    });

    panel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    // Grow the textarea with its content, up to its max height.
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${input.scrollHeight}px`;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || busy || !enabled) return;

        input.value = '';
        input.style.height = '';
        bubble('user', text);
        await send(text);
        input.focus();
    });

    accessForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const field = accessForm.querySelector('input');
        const res = await api.access(field.value);

        if (res.kind === 'ok') {
            field.value = '';
            accessForm.hidden = true;
            form.hidden = false;
            notice('Unlocked. You can chat now.');
            if (pendingMessage) {
                const text = pendingMessage;
                pendingMessage = null;
                await send(text);
            }
            input.focus();
        } else {
            notice(errorMessage(res.data) || 'That code did not work.');
        }
    });

    win.visualViewport?.addEventListener('resize', () => {
        if (!panel.hidden) fitToViewport();
    });

    // On desktop, reopen after navigating (e.g. following a suggested product) so the conversation carries on.
    // Not on phones: the full-screen sheet would hide the page the customer just opened.
    if (session.get(OPEN_KEY) === '1' && !win.matchMedia('(max-width: 639px)').matches) open({ focus: false });
}
