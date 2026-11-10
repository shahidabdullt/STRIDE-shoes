import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Resampler, base64ToBytes, bytesToBase64, floatToPcm16Bytes, pcm16BytesToFloat, rms } from '../../resources/js/voice/pcm.js';
import { TranscriptBatcher, MAX_SEGMENT_CHARS } from '../../resources/js/voice/transcripts.js';
import { LiveConnection, isTokenProblem, parseFrame, routeMessage } from '../../resources/js/voice/live-client.js';
import { createVoiceApi } from '../../resources/js/voice/api.js';

test('float samples become PCM16 little-endian and back', () => {
    const bytes = floatToPcm16Bytes(Float32Array.from([0, 1, -1, 0.5, 2, -2]));
    assert.equal(bytes.length, 12);
    assert.deepEqual([...bytes.slice(0, 6)], [0, 0, 0xff, 0x7f, 0x00, 0x80]); // 0, 32767, -32768 (LE)
    const back = pcm16BytesToFloat(bytes);
    assert.equal(back[0], 0);
    assert.ok(Math.abs(back[1] - 1) < 0.001);
    assert.equal(back[2], -1);
    assert.ok(Math.abs(back[3] - 0.5) < 0.001);
    assert.ok(back[4] < 1 && back[5] === -1, 'out-of-range input is clamped');
});

test('base64 round trip, including buffers larger than the chunk size', () => {
    const bytes = new Uint8Array(100000).map((_, i) => i % 256);
    assert.deepEqual(base64ToBytes(bytesToBase64(bytes)), bytes);
    assert.equal(bytesToBase64(Uint8Array.from([104, 105])), 'aGk=');
});

test('48 kHz resamples to 16 kHz with the right count across blocks', () => {
    const r = new Resampler(48000, 16000);
    let total = 0;
    for (let i = 0; i < 25; i++) total += r.process(new Float32Array(1920)).length; // 25 x 40 ms = 1 s
    assert.equal(total, 16000);
});

test('44.1 kHz resampling keeps the sample count within one of exact and preserves a constant', () => {
    const r = new Resampler(44100, 16000);
    let total = 0;
    let last;
    for (let i = 0; i < 10; i++) {
        last = r.process(new Float32Array(4410).fill(0.25));
        total += last.length;
    }
    assert.ok(Math.abs(total - 16000) <= 1, `got ${total}`);
    assert.ok(last.every((v) => Math.abs(v - 0.25) < 1e-6));
});

test('equal rates pass through unchanged', () => {
    const input = Float32Array.from([0.1, 0.2]);
    assert.deepEqual(new Resampler(16000, 16000).process(input), input);
    assert.equal(rms(Float32Array.from([0.5, -0.5])), 0.5);
    assert.equal(rms(new Float32Array(0)), 0);
});

test('transcript fragments join per speaker and turn, customer first', () => {
    const b = new TranscriptBatcher({ now: () => new Date('2026-11-08T10:00:00Z') });
    b.add('assistant', 'Stride here. I ');
    b.add('user', 'Do you have ');
    b.add('assistant', 'can help.');
    b.add('user', 'trail  shoes?');
    assert.equal(b.live('assistant'), 'Stride here. I can help.');
    assert.deepEqual(b.take(), [], 'nothing is ready before the turn ends');

    b.endTurn();
    assert.deepEqual(b.take(), [
        { role: 'user', text: 'Do you have trail shoes?', at: '2026-11-08T10:00:00.000Z' },
        { role: 'assistant', text: 'Stride here. I can help.', at: '2026-11-08T10:00:00.000Z' },
    ]);
    assert.equal(b.live('user'), '');
});

test('long speech is split into 2000-character segments and batches hold at most 50', () => {
    const b = new TranscriptBatcher();
    b.add('assistant', 'a'.repeat(MAX_SEGMENT_CHARS * 2 + 10));
    b.endTurn();
    assert.deepEqual(b.take().map((s) => s.text.length), [2000, 2000, 10]);

    for (let i = 0; i < 60; i++) { b.add('user', `q${i}`); b.endTurn(); }
    assert.equal(b.take().length, 50);
    assert.equal(b.take().length, 10);
});

test('takeAll includes unfinished speech', () => {
    const b = new TranscriptBatcher();
    b.add('user', 'half a sen');
    assert.deepEqual(b.takeAll().map((s) => s.text), ['half a sen']);
});

test('server messages are routed; unknown types are ignored', () => {
    const seen = [];
    const h = new Proxy({}, { get: (_, name) => (...args) => seen.push([name, ...args]) });

    routeMessage({ setupComplete: {} }, h);
    routeMessage({ serverContent: { modelTurn: { parts: [{ inlineData: { data: 'AAA=', mimeType: 'audio/pcm;rate=24000' } }, { text: 'x' }] } } }, h);
    routeMessage({ serverContent: { inputTranscription: { text: 'hi' }, outputTranscription: { text: 'hello' } } }, h);
    routeMessage({ serverContent: { interrupted: true } }, h);
    routeMessage({ serverContent: { generationComplete: true, turnComplete: true } }, h);
    routeMessage({ toolCall: { functionCalls: [{ id: 'c1', name: 'view_cart', args: {} }] } }, h);
    routeMessage({ toolCallCancellation: { ids: ['c1'] } }, h);
    routeMessage({ goAway: { timeLeft: '10s' } }, h);
    routeMessage({ sessionResumptionUpdate: { resumable: false, newHandle: 'ignored' } }, h);
    routeMessage({ sessionResumptionUpdate: { resumable: true, newHandle: 'h2' } }, h);
    routeMessage({ usageMetadata: { promptTokenCount: 5 } }, h);
    routeMessage({ voiceActivity: { type: 'START' } }, h);
    routeMessage(null, h);

    assert.deepEqual(seen.map((s) => s[0]), [
        'setupComplete', 'audio', 'inputText', 'outputText', 'interrupted', 'turnComplete',
        'toolCall', 'toolCancel', 'goAway', 'handle', 'usage',
    ]);
    assert.equal(seen[1][1], 'AAA=');
    assert.equal(seen.find((s) => s[0] === 'handle')[1], 'h2');
});

test('frames are parsed from text or binary', async () => {
    assert.deepEqual(await parseFrame('{"a":1}'), { a: 1 });
    assert.deepEqual(await parseFrame(new Blob(['{"b":2}'])), { b: 2 });
    assert.deepEqual(await parseFrame(new TextEncoder().encode('{"c":3}').buffer), { c: 3 });
    assert.equal(await parseFrame('not json'), null);
});

test('token problems are told apart from network failures', () => {
    assert.ok(isTokenProblem('Token has been used too many times'));
    assert.ok(isTokenProblem('Auth token expired'));
    assert.ok(!isTokenProblem(''));
    assert.ok(!isTokenProblem('Internal error'));
});

class FakeSocket {
    static last;
    constructor(url) { this.url = url; this.sent = []; this.readyState = 1; FakeSocket.last = this; setTimeout(() => this.onopen?.(), 0); }
    send(data) { this.sent.push(JSON.parse(data)); }
    close() { this.onclose?.({ code: 1000, reason: '' }); }
    receive(message) { this.onmessage?.({ data: JSON.stringify(message) }); }
}

test('LiveConnection sends only model and resumption in setup, then the documented client messages', async () => {
    const closes = [];
    const c = new LiveConnection({ url: 'wss://x/y', token: 'auth_tokens/a b', model: 'models/gemini-3.8-live', handle: 'h1', handlers: { close: (i) => closes.push(i) }, WebSocketImpl: FakeSocket });
    const opened = c.open();
    await new Promise((r) => setTimeout(r, 5));
    const ws = FakeSocket.last;

    assert.equal(ws.url, 'wss://x/y?access_token=auth_tokens%2Fa%20b');
    assert.deepEqual(ws.sent[0], { setup: { model: 'models/gemini-3.8-live', sessionResumption: { handle: 'h1' } } });

    c.sendAudio('QUJD');
    assert.equal(ws.sent.length, 1, 'nothing is sent before setupComplete');

    ws.receive({ setupComplete: {} });
    await opened;
    c.sendAudio('QUJD');
    c.activityStart();
    c.activityEnd();
    c.audioStreamEnd();
    c.toolResponse([{ id: 'c1', name: 'view_cart', response: { items: [] } }]);
    assert.deepEqual(ws.sent.slice(1), [
        { realtimeInput: { audio: { data: 'QUJD', mimeType: 'audio/pcm;rate=16000' } } },
        { realtimeInput: { activityStart: {} } },
        { realtimeInput: { activityEnd: {} } },
        { realtimeInput: { audioStreamEnd: true } },
        { toolResponse: { functionResponses: [{ id: 'c1', name: 'view_cart', response: { items: [] } }] } },
    ]);

    c.close();
    assert.deepEqual(closes, [{ code: 1000, reason: '', byUs: true }]);
});

test('LiveConnection rejects when the socket closes before setupComplete', async () => {
    const c = new LiveConnection({ url: 'wss://x', token: 't', model: 'm', handlers: {}, WebSocketImpl: FakeSocket });
    const opened = c.open();
    await new Promise((r) => setTimeout(r, 5));
    FakeSocket.last.onclose({ code: 1011, reason: 'Token has been used too many times' });
    await assert.rejects(opened, (e) => e.code === 1011 && isTokenProblem(e.reason));
});

test('voice API posts to the documented endpoints', async () => {
    const calls = [];
    const api = createVoiceApi({ fetchImpl: async (url, init) => { calls.push([url, JSON.parse(init.body)]); return { status: 200, json: async () => ({}) }; } });
    await api.start('hold_to_talk', 'mobile');
    await api.toolCall('u-1', { id: 'c9', name: 'add_to_cart', args: { slug: 's', size: 9 } });
    await api.transcripts('u-1', [{ role: 'user', text: 'hi' }]);
    await api.end('u-1', { reason: 'user_ended' });

    assert.deepEqual(calls, [
        ['/voice/sessions', { mode: 'hold_to_talk', device_class: 'mobile' }],
        ['/voice/sessions/u-1/tool-calls', { call_id: 'c9', name: 'add_to_cart', args: { slug: 's', size: 9 } }],
        ['/voice/sessions/u-1/transcripts', { segments: [{ role: 'user', text: 'hi' }] }],
        ['/voice/sessions/u-1/end', { reason: 'user_ended' }],
    ]);
    assert.equal(api.endUrl('u-1'), '/voice/sessions/u-1/end');
});
