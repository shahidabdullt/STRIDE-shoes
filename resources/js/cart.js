// Progressive enhancement: forms marked data-cart-form submit with fetch so the header badge
// updates without a page reload. Without JavaScript they post normally and redirect back.

import { jsonHeaders } from './lib/csrf.js';

export function showCartMessage(doc, text, ok) {
    const box = doc.querySelector('[data-cart-message]');
    if (!box) return;
    box.textContent = text;
    box.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-900', 'border-red-200', 'bg-red-50', 'text-red-900');
    box.classList.add(...(ok ? ['border-emerald-200', 'bg-emerald-50', 'text-emerald-900'] : ['border-red-200', 'bg-red-50', 'text-red-900']));
    box.setAttribute('role', ok ? 'status' : 'alert');
}

export function updateCartBadge(doc, count) {
    for (const badge of doc.querySelectorAll('[data-cart-count]')) {
        badge.textContent = String(count);
        // `hidden` and `inline-flex` both set display, so exactly one of them must be present.
        badge.classList.toggle('hidden', count < 1);
        badge.classList.toggle('inline-flex', count >= 1);
    }
}

export function initCartForms(doc = document) {
    doc.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-cart-form]');
        if (!form) return;

        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        if (submit) submit.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: jsonHeaders(doc),
                body: JSON.stringify(Object.fromEntries(new FormData(form))),
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                updateCartBadge(doc, data.cart.count);
                showCartMessage(doc, data.message, true);
            } else {
                const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                showCartMessage(doc, firstError || data.message || 'Something went wrong. Please try again.', false);
            }
        } catch {
            showCartMessage(doc, 'Could not reach the shop. Check your connection and try again.', false);
        } finally {
            if (submit) submit.disabled = false;
        }
    });
}
