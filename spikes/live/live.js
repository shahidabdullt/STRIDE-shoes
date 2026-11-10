// Phase 0 spike client for the Gemini Live API over a raw WebSocket. Throwaway code.
// Covers: S1 lock test, S2 audio in/out + barge-in + hold-to-talk, S3 tool relay, S5 resumption.

const $ = (id) => document.getElementById(id);
const t0 = performance.now();
const now = () => performance.now();

const state = {
    token: null, wsUrl: null, model: null, mode: 'hands_free',
    ws: null, handle: null, resumable: false,
    captureCtx: null, playCtx: null, stream: null, worklet: null, sendRate: 16000,
    streaming: false, pressed: false, preBuffer: [],
    playing: new Set(), nextPlayTime: 0,
    lastSpeechAt: 0, speaking: false, speechThreshold: 0.02,
    awaitingFirstAudio: false, turnEndAt: 0, latencies: [],
    pendingTools: new Map(),
};

// ---------- logging ----------

function log(msg, obj) {
    const line = `[${((now() - t0) / 1000).toFixed(2)}s] ${msg}` + (obj !== undefined ? ' ' + JSON.stringify(obj) : '');
    $('log').textContent += line + '\n';
    $('log').scrollTop = $('log').scrollHeight;
}
function transcript(who, text) {
    $('transcript').textContent += `${who}: ${text}\n`;
    $('transcript').scrollTop = $('transcript').scrollHeight;
}
function setStatus(s) { $('status').textContent = s; }
const median = (a) => { const s = [...a].sort((x, y) => x - y); return s.length ? s[Math.floor(s.length / 2)] : null; };

// ---------- PCM helpers ----------

function bytesToBase64(bytes) {
    let bin = '';
    for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    return btoa(bin);
}
function base64ToFloat32(b64) {
    const bin = atob(b64);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    const pcm = new Int16Array(bytes.buffer, 0, bytes.length >> 1);
    const out = new Float32Array(pcm.length);
    for (let i = 0; i < pcm.length; i++) out[i] = pcm[i] / 0x8000;
    return out;
}

// ---------- audio ----------

async function startAudio() {
    state.playCtx = new AudioContext({ sampleRate: 24000 });
    state.stream = await navigator.mediaDevices.getUserMedia({
        audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true },
    });
    const track = state.stream.getAudioTracks()[0];
    log('mic settings', track.getSettings());

    state.captureCtx = new AudioContext();
    await state.captureCtx.audioWorklet.addModule('capture-worklet.js');
    const source = state.captureCtx.createMediaStreamSource(state.stream);
    state.worklet = new AudioWorkletNode(state.captureCtx, 'capture-processor', {
        processorOptions: { targetRate: $('downsample').checked ? 16000 : 0 },
    });
    state.worklet.port.onmessage = (e) => onWorkletMessage(e.data);
    source.connect(state.worklet);
    // Chrome only runs an AudioWorklet that is pulled by the destination. A silent gain
    // node keeps the mic audio from being played back while still driving the processor.
    const silent = state.captureCtx.createGain();
    silent.gain.value = 0;
    state.worklet.connect(silent).connect(state.captureCtx.destination);

    if (state.playCtx.state !== 'running') await state.playCtx.resume();
    if (state.captureCtx.state !== 'running') await state.captureCtx.resume();
    log(`audio contexts: capture=${state.captureCtx.state}, playback=${state.playCtx.state}`);
}

function onWorkletMessage(msg) {
    try {
        if (msg.type === 'rate') {
            state.sendRate = msg.rate;
            log(`capture: context ${msg.contextRate} Hz, sending ${msg.rate} Hz`);
        } else if (msg.type === 'level') {
            state.levelMessages = (state.levelMessages || 0) + 1;
            if (state.levelMessages === 1) log('capture worklet is running (first level message)');
            if (state.levelMessages % 200 === 0) log(`level messages: ${state.levelMessages}, streaming=${state.streaming}, ws=${state.ws?.readyState}`);
            $('meter').firstElementChild.style.width = Math.min(100, msg.rms * 400) + '%';
            trackSpeech(msg.rms);
        } else if (msg.type === 'pcm') {
            state.pcmMessages = (state.pcmMessages || 0) + 1;
            if (state.pcmMessages === 1) log('first 40 ms PCM chunk produced by the worklet');
            if (state.pcmMessages % 50 === 0) log(`PCM chunks produced: ${state.pcmMessages}, streaming=${state.streaming}, sent=${state.sentChunks || 0}`);
            onPcmChunk(new Uint8Array(msg.buffer));
        }
    } catch (e) {
        log(`ERROR handling worklet message: ${e.name}: ${e.message}`);
        console.error(e);
    }
}

function trackSpeech(rms) {
    const loud = rms > state.speechThreshold;
    if (loud) {
        if (!state.speaking && state.playing.size > 0) {
            state.bargeInAt = now();
            log('local speech onset while model audio playing');
        }
        state.speaking = true;
        state.lastSpeechAt = now();
    } else if (state.speaking && now() - state.lastSpeechAt > 300) {
        state.speaking = false;
    }
}

function onPcmChunk(bytes) {
    if (state.mode === 'hold_to_talk' && !state.pressed) {
        // Rolling ~300 ms pre-buffer (40 ms chunks) sent right after activityStart.
        state.preBuffer.push(bytes);
        if (state.preBuffer.length > 8) state.preBuffer.shift();
        return;
    }
    if (state.streaming) sendAudio(bytes);
}

function sendAudio(bytes) {
    send({ realtimeInput: { audio: { data: bytesToBase64(bytes), mimeType: `audio/pcm;rate=${state.sendRate}` } } }, true);
    state.sentChunks = (state.sentChunks || 0) + 1;
    if (state.sentChunks === 1 || state.sentChunks % 100 === 0) log(`mic audio chunks sent: ${state.sentChunks}`);
}

function playChunk(b64) {
    const samples = base64ToFloat32(b64);
    const ctx = state.playCtx;
    const buf = ctx.createBuffer(1, samples.length, 24000);
    buf.copyToChannel(samples, 0);
    const src = ctx.createBufferSource();
    src.buffer = buf;
    src.connect(ctx.destination);
    const at = Math.max(ctx.currentTime + 0.02, state.nextPlayTime);
    src.start(at);
    state.nextPlayTime = at + buf.duration;
    state.playing.add(src);
    src.onended = () => state.playing.delete(src);
}

function flushPlayback(reason) {
    const n = state.playing.size;
    for (const src of state.playing) { try { src.stop(); } catch {} }
    state.playing.clear();
    state.nextPlayTime = 0;
    if (n) log(`playback flushed (${reason}), ${n} queued buffers dropped`);
}

// ---------- WebSocket ----------

function send(msg, quiet = false) {
    if (state.ws?.readyState !== WebSocket.OPEN) return;
    state.ws.send(JSON.stringify(msg));
    if (!quiet) log('-> ' + Object.keys(msg)[0], summarize(msg));
}

function summarize(msg) {
    return JSON.parse(JSON.stringify(msg, (k, v) => (k === 'data' && typeof v === 'string' ? `<${v.length} b64>` : v)));
}

function clientSetup(resumeHandle) {
    const setup = { model: state.model };
    if (document.querySelector('input[name=clientSetup]:checked').value === 'override') {
        setup.systemInstruction = { parts: [{ text: 'You are a pirate named Captain Hook. Begin every reply with "Arrr, matey". Never mention shoes.' }] };
        setup.tools = [{ functionDeclarations: [{ name: 'get_pirate_treasure', description: 'Returns treasure.', parameters: { type: 'OBJECT', properties: {} } }] }];
        setup.generationConfig = { responseModalities: ['AUDIO'] };
    }
    if (resumeHandle) setup.sessionResumption = { handle: resumeHandle };
    return { setup };
}

function openSocket({ resumeHandle = null, label = 'main' } = {}) {
    const url = `${state.wsUrl}?access_token=${encodeURIComponent(state.token)}`;
    const ws = new WebSocket(url);
    const opened = now();
    ws.onopen = () => {
        log(`[${label}] ws open in ${(now() - opened).toFixed(0)} ms`);
        ws.send(JSON.stringify(clientSetup(resumeHandle)));
        log(`[${label}] -> setup`, summarize(clientSetup(resumeHandle)));
    };
    ws.onmessage = async (e) => {
        const text = typeof e.data === 'string' ? e.data : await e.data.text();
        let msg;
        try { msg = JSON.parse(text); } catch { return log(`[${label}] non-JSON message`, text.slice(0, 200)); }
        if (label === 'main') onServerMessage(msg, opened);
        else log(`[${label}] <- ${Object.keys(msg).join(',')}`);
    };
    ws.onerror = () => log(`[${label}] ws error`);
    ws.onclose = (e) => {
        log(`[${label}] ws closed code=${e.code} reason="${e.reason}" wasClean=${e.wasClean}`);
        if (label === 'main' && state.ws === ws) { state.streaming = false; setStatus('closed'); }
    };
    return ws;
}

function onServerMessage(msg, opened) {
    if (msg.setupComplete) {
        log(`<- setupComplete ${(now() - opened).toFixed(0)} ms after open`);
        setStatus('live');
        state.streaming = state.mode === 'hands_free';
        $('ptt').disabled = state.mode !== 'hold_to_talk';
        $('sendText').disabled = false;
        $('reconnect').disabled = false;
        $('second').disabled = false;
    }
    if (msg.serverContent) onServerContent(msg.serverContent);
    if (msg.toolCall) onToolCall(msg.toolCall);
    if (msg.toolCallCancellation) {
        log('<- toolCallCancellation', msg.toolCallCancellation);
        for (const id of msg.toolCallCancellation.ids || []) state.pendingTools.get(id)?.abort();
    }
    if (msg.goAway) {
        log('<- goAway', msg.goAway);
        if ($('autoResume').checked && state.handle) reconnect('goAway');
    }
    if (msg.sessionResumptionUpdate) {
        const u = msg.sessionResumptionUpdate;
        state.resumable = !!u.resumable;
        if (u.newHandle) state.handle = u.newHandle;
        log('<- sessionResumptionUpdate', { resumable: u.resumable, hasHandle: !!u.newHandle });
    }
    if (msg.usageMetadata) log('<- usageMetadata', msg.usageMetadata);
    const other = Object.keys(msg).filter((k) => !['setupComplete', 'serverContent', 'toolCall', 'toolCallCancellation', 'goAway', 'sessionResumptionUpdate', 'usageMetadata'].includes(k));
    if (other.length) log('<- other fields', other);
}

function onServerContent(sc) {
    for (const part of sc.modelTurn?.parts || []) {
        if (part.inlineData?.data) {
            if (state.awaitingFirstAudio) {
                // turnEndAt is null in hands-free mode: use the last locally detected speech.
                const ms = now() - (state.turnEndAt ?? state.lastSpeechAt);
                state.awaitingFirstAudio = false;
                state.latencies.push(ms);
                log(`first model audio ${ms.toFixed(0)} ms after end of user speech (median ${median(state.latencies).toFixed(0)} ms over ${state.latencies.length}); mime ${part.inlineData.mimeType}`);
            }
            playChunk(part.inlineData.data);
        } else if (part.text) {
            log('<- model text part', part.text.slice(0, 200));
        }
    }
    if (sc.inputTranscription?.text) transcript('user', sc.inputTranscription.text);
    if (sc.outputTranscription?.text) transcript('model', sc.outputTranscription.text);
    if (sc.interrupted) {
        const since = state.bargeInAt ? ` (${(now() - state.bargeInAt).toFixed(0)} ms after local speech onset)` : '';
        log('<- interrupted' + since);
        flushPlayback('interrupted');
        state.bargeInAt = 0;
    }
    if (sc.generationComplete) log('<- generationComplete');
    if (sc.turnComplete) {
        log('<- turnComplete');
        transcript('---', '');
    }
    // Hands-free: estimate end of user speech from the local level meter.
    if (state.mode === 'hands_free' && sc.inputTranscription && !state.awaitingFirstAudio && state.playing.size === 0) {
        state.awaitingFirstAudio = true;
        state.turnEndAt = null;
    }
}

async function onToolCall(tc) {
    const received = now();
    log('<- toolCall', tc);
    const responses = await Promise.all((tc.functionCalls || []).map(async (fc) => {
        const ctrl = new AbortController();
        state.pendingTools.set(fc.id, ctrl);
        try {
            const res = await fetch(`tool.php?delay_ms=${$('toolDelay').value}`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: fc.name, args: fc.args }), signal: ctrl.signal,
            });
            return { id: fc.id, name: fc.name, response: await res.json() };
        } catch (e) {
            log(`tool ${fc.id} aborted/failed: ${e.name}`);
            return null;
        } finally {
            state.pendingTools.delete(fc.id);
        }
    }));
    const ok = responses.filter(Boolean);
    if (!ok.length) return;
    send({ toolResponse: { functionResponses: ok } });
    log(`tool round trip (toolCall received -> toolResponse sent) ${(now() - received).toFixed(0)} ms`);
    state.awaitingFirstAudio = true;
    state.turnEndAt = now();
}

// ---------- controls ----------

async function connect() {
    $('connect').disabled = true;
    setStatus('requesting token');
    state.mode = $('mode').value;
    const q = new URLSearchParams({ variant: $('variant').value, mode: state.mode, nst: $('nst').value, uses: $('uses').value });
    const res = await fetch('token.php?' + q);
    const body = await res.json();
    if (!res.ok) {
        log('token mint failed', body);
        setStatus('token error');
        $('connect').disabled = false;
        return;
    }
    Object.assign(state, { token: body.token, wsUrl: body.ws_url, model: body.model, handle: null });
    log(`token minted: variant=${body.variant} mode=${body.mode} in ${body.mint_ms} ms (value not logged)`);

    try {
        if (!state.captureCtx) await startAudio();
    } catch (e) {
        log('mic/audio error: ' + e.name + ' ' + e.message);
        setStatus('mic error');
        $('connect').disabled = false;
        return;
    }
    setStatus('connecting');
    state.ws = openSocket();
    $('disconnect').disabled = false;
}

function reconnect(why) {
    log(`reconnecting (${why}) with ${state.handle ? 'resumption handle' : 'NO handle'}`);
    const old = state.ws;
    state.ws = null;
    old?.close(1000, 'client reconnect');
    flushPlayback('reconnect');
    setStatus('reconnecting');
    state.ws = openSocket({ resumeHandle: state.handle });
}

function disconnect() {
    state.ws?.close(1000, 'user ended');
    flushPlayback('disconnect');
    state.stream?.getTracks().forEach((t) => t.stop());
    state.captureCtx?.close();
    state.playCtx?.close();
    Object.assign(state, { ws: null, captureCtx: null, playCtx: null, stream: null, streaming: false });
    $('connect').disabled = false;
    for (const id of ['disconnect', 'reconnect', 'second', 'ptt', 'sendText']) $(id).disabled = true;
    setStatus('ended');
}

$('connect').onclick = () => connect().catch((e) => log('connect error ' + e));
$('disconnect').onclick = disconnect;
$('reconnect').onclick = () => reconnect('manual');
$('second').onclick = () => openSocket({ label: 'second' });
$('sendText').onclick = () => {
    flushPlayback('new text turn');
    send({ clientContent: { turns: [{ role: 'user', parts: [{ text: $('text').value }] }], turnComplete: true } });
    transcript('user(text)', $('text').value);
    state.awaitingFirstAudio = true;
    state.turnEndAt = now();
};
$('copy').onclick = () => navigator.clipboard.writeText($('log').textContent + '\n--- transcripts ---\n' + $('transcript').textContent);

// Hold-to-talk with pointer events (mouse and touch).
const ptt = $('ptt');
ptt.addEventListener('pointerdown', (e) => {
    if (ptt.disabled) return;
    ptt.setPointerCapture(e.pointerId);
    ptt.classList.add('active');
    flushPlayback('hold-to-talk press');
    state.pressed = true;
    send({ realtimeInput: { activityStart: {} } });
    const pre = state.preBuffer.splice(0);
    pre.forEach(sendAudio);
    log(`sent ${pre.length} pre-buffer chunks (${pre.length * 40} ms)`);
    state.streaming = true;
});
const release = () => {
    if (!state.pressed) return;
    state.pressed = false;
    state.streaming = false;
    ptt.classList.remove('active');
    send({ realtimeInput: { activityEnd: {} } });
    state.awaitingFirstAudio = true;
    state.turnEndAt = now();
};
ptt.addEventListener('pointerup', release);
ptt.addEventListener('pointercancel', release);

document.addEventListener('visibilitychange', () => log('visibility: ' + document.visibilityState));
