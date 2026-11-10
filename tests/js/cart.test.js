import assert from 'node:assert/strict';
import { test } from 'node:test';

import { showCartMessage, updateCartBadge } from '../../resources/js/cart.js';

// A tiny stand-in for the parts of the DOM these helpers touch.
function fakeElement() {
    const classes = new Set(['hidden']);
    const attributes = {};

    return {
        textContent: '',
        classList: {
            add: (...names) => names.forEach((n) => classes.add(n)),
            remove: (...names) => names.forEach((n) => classes.delete(n)),
            toggle: (name, force) => (force ? classes.add(name) : classes.delete(name)),
            contains: (name) => classes.has(name),
        },
        setAttribute: (name, value) => (attributes[name] = value),
        attributes,
    };
}

function fakeDoc({ message, badges = [] }) {
    return {
        querySelector: (selector) => (selector === '[data-cart-message]' ? message : null),
        querySelectorAll: (selector) => (selector === '[data-cart-count]' ? badges : []),
    };
}

test('updateCartBadge shows the count and hides the badge at zero', () => {
    const badge = fakeElement();
    const doc = fakeDoc({ badges: [badge] });

    updateCartBadge(doc, 3);
    assert.equal(badge.textContent, '3');
    assert.equal(badge.classList.contains('hidden'), false);
    assert.equal(badge.classList.contains('inline-flex'), true);

    updateCartBadge(doc, 0);
    assert.equal(badge.classList.contains('hidden'), true);
    assert.equal(badge.classList.contains('inline-flex'), false);
});

test('showCartMessage uses a status role for success and an alert role for errors', () => {
    const box = fakeElement();
    const doc = fakeDoc({ message: box });

    showCartMessage(doc, 'Added to your cart.', true);
    assert.equal(box.textContent, 'Added to your cart.');
    assert.equal(box.attributes.role, 'status');
    assert.equal(box.classList.contains('hidden'), false);
    assert.equal(box.classList.contains('bg-emerald-50'), true);

    showCartMessage(doc, 'Sorry, sold out.', false);
    assert.equal(box.attributes.role, 'alert');
    assert.equal(box.classList.contains('bg-red-50'), true);
    assert.equal(box.classList.contains('bg-emerald-50'), false);
});

test('showCartMessage does nothing when the page has no message box', () => {
    assert.doesNotThrow(() => showCartMessage(fakeDoc({}), 'hello', true));
});
