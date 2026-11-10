import assert from 'node:assert/strict';
import { test } from 'node:test';

import { csrfToken, jsonHeaders } from '../../resources/js/lib/csrf.js';

const docWith = (content) => ({
    querySelector: (selector) => (selector === 'meta[name="csrf-token"]' && content !== null ? { content } : null),
});

test('csrfToken returns the meta tag value', () => {
    assert.equal(csrfToken(docWith('abc123')), 'abc123');
});

test('csrfToken returns null when the meta tag is missing', () => {
    assert.equal(csrfToken(docWith(null)), null);
});

test('jsonHeaders includes the CSRF header only when a token exists', () => {
    assert.equal(jsonHeaders(docWith('abc123'))['X-CSRF-TOKEN'], 'abc123');
    assert.equal('X-CSRF-TOKEN' in jsonHeaders(docWith(null)), false);
    assert.equal(jsonHeaders(docWith(null)).Accept, 'application/json');
});
