import { test } from 'node:test';
import assert from 'node:assert/strict';
import { classify, createChatApi, errorMessage } from '../../resources/js/chat/api.js';
import { safeStorage } from '../../resources/js/chat/storage.js';

function fakeFetch(status, body) {
    const calls = [];
    const fetchImpl = async (url, init) => {
        calls.push({ url, init });
        return { status, json: async () => body };
    };
    return { fetchImpl, calls };
}

test('status codes map to the kinds the widget handles', () => {
    assert.equal(classify(200, {}), 'ok');
    assert.equal(classify(201, {}), 'ok');
    assert.equal(classify(204, null), 'ok');
    assert.equal(classify(403, { error: 'access_code_required' }), 'access_required');
    assert.equal(classify(403, {}), 'error');
    assert.equal(classify(404, null), 'not_found');
    assert.equal(classify(409, {}), 'ended');
    assert.equal(classify(419, {}), 'expired');
    assert.equal(classify(422, {}), 'invalid');
    assert.equal(classify(429, {}), 'rate_limited');
    assert.equal(classify(503, {}), 'unavailable');
    assert.equal(classify(500, {}), 'error');
});

test('errorMessage prefers the first validation error, then message', () => {
    assert.equal(errorMessage({ errors: { message: ['Too long.'] }, message: 'The given data was invalid.' }), 'Too long.');
    assert.equal(errorMessage({ message: 'Slow down' }), 'Slow down');
    assert.equal(errorMessage(null), null);
});

test('send posts JSON to the conversation messages endpoint with the given headers', async () => {
    const { fetchImpl, calls } = fakeFetch(200, { reply: 'Hi', cart: { count: 1 }, products: [] });
    const api = createChatApi({ fetchImpl, headers: () => ({ 'X-CSRF-TOKEN': 't' }) });

    const res = await api.send('abc-123', 'Hello');

    assert.equal(res.kind, 'ok');
    assert.equal(res.data.reply, 'Hi');
    assert.equal(calls[0].url, '/assistant/conversations/abc-123/messages');
    assert.equal(calls[0].init.method, 'POST');
    assert.equal(calls[0].init.headers['X-CSRF-TOKEN'], 't');
    assert.deepEqual(JSON.parse(calls[0].init.body), { message: 'Hello' });
});

test('uuids are URL-encoded and GET sends no body', async () => {
    const { fetchImpl, calls } = fakeFetch(200, { messages: [] });
    await createChatApi({ fetchImpl }).load('../admin?x=1');

    assert.equal(calls[0].url, '/assistant/conversations/..%2Fadmin%3Fx%3D1');
    assert.equal(calls[0].init.body, undefined);
});

test('a network failure resolves to kind network instead of throwing', async () => {
    const api = createChatApi({ fetchImpl: async () => { throw new TypeError('Failed to fetch'); } });
    assert.deepEqual(await api.start('mobile'), { kind: 'network', status: 0, data: null });
});

test('a non-JSON error body still classifies', async () => {
    const api = createChatApi({ fetchImpl: async () => ({ status: 500, json: async () => { throw new SyntaxError('bad'); } }) });
    const res = await api.end('x');
    assert.equal(res.kind, 'error');
    assert.equal(res.data, null);
});

test('safeStorage survives storage that throws on access', () => {
    const blocked = safeStorage(() => { throw new Error('SecurityError'); });
    assert.equal(blocked.get('k'), null);
    blocked.set('k', 'v');
    blocked.remove('k');

    const map = new Map();
    const working = safeStorage(() => ({ getItem: (k) => map.get(k) ?? null, setItem: (k, v) => map.set(k, v), removeItem: (k) => map.delete(k) }));
    working.set('k', 'v');
    assert.equal(working.get('k'), 'v');
    working.remove('k');
    assert.equal(working.get('k'), null);
});
