// The voice panel (markup: resources/views/components/voice-panel.blade.php). State machine:
// idle -> starting -> connecting -> live <-> reconnecting -> ended | error   (BLUEPRINT Phase 5)

import { jsonHeaders, csrfToken } from '../lib/csrf.js';
import { updateCartBadge } from '../cart.js';
import { errorMessage } from '../chat/api.js';
import { createVoiceApi } from './api.js';
import { Playback } from './audio-playback.js';
import { LiveConnection, isTokenProblem } from './live-client.js';
import { INPUT_RATE, Resampler, bytesToBase64, floatToPcm16Bytes, rms } from './pcm.js';
import { TranscriptBatcher } from './transcripts.js';

const PREBUFFER_CHUNKS = 8; // ~320 ms of 40 ms chunks, sent right after activityStart (manual VAD)
const TRANSCRIPT_FLUSH_MS = 5000;
const MAX_RECONNECTS = 2;

export function initVoice(doc = document, win = window) {
    const root = doc.querySelector('[data-voice]');
    if (!root) return;

    const $ = (selector) => root.querySelector(selector);
    const ui = {
        launcher: $('[data-voice-launcher]'),
        panel: $('[data-voice-panel]'),
        intro: $('[data-voice-intro]'),
        live: $('[data-voice-live]'),
        message: $('[data-voice-message]'),
        messageText: $('[data-voice-message-text]'),
        status: $('[data-voice-status]'),
        captions: $('[data-voice-captions]'),
        hold: $('[data-voice-hold]'),
        meter: $('[data-voice-meter]'),
        timer: $('[data-voice-timer]'),
        start: $('[data-voice-start]'),
    };
    const api = createVoiceApi({ headers: () => jsonHeaders(doc) });
    const enabled = root.dataset.enabled === '1';
    const touch = win.matchMedia('(pointer: coarse)').matches;
    const defaultMode = touch ? root.dataset.defaultMobile : root.dataset.defaultDesktop;
    const modeInput = root.querySelector(`input[name="voice-mode"][value="${defaultMode}"]`) ?? root.querySelector('input[name="voice-mode"]');
    modeInput.checked = true;

    let s = null; // the current session

    // ---- views ---------------------------------------------------------------------------------

    function show(view) {
        ui.intro.hidden = view !== 'intro';
        ui.live.hidden = view !== 'live';
        ui.message.hidden = view !== 'message';
    }

    function setStatus(text) {
        ui.status.textContent = text;
    }

    function showMessage(text) {
        ui.messageText.textContent = text;
        ui.timer.classList.add('hidden');
        show('message');
    }

    function caption(role, text, element = null) {
        const line = element ?? doc.createElement('p');
        line.className = role === 'user' ? 'text-right text-stone-600' : 'text-left text-stone-900';
        line.textContent = text;
        if (!element) ui.captions.append(line);
        ui.captions.scrollTop = ui.captions.scrollHeight;
        return line;
    }

    // Live captions: one line per speaker per turn, updated as fragments arrive.
    function updateCaptions() {
        for (const role of ['user', 'assistant']) {
            const text = s.batcher.live(role);
            if (!text) continue;
            s.captionLines[role] = caption(role, (role === 'user' ? 'You: ' : 'Stride: ') + text, s.captionLines[role]);
        }
    }

    // ---- open / close ----------------------------------------------------------------------------

    function open() {
        ui.panel.hidden = false;
        ui.launcher.hidden = true;
        ui.launcher.setAttribute('aria-expanded', 'true');
        if (!s) {
            show(enabled ? 'intro' : 'message');
            if (!enabled) showMessage('Voice is not available right now. You can still use the text chat.');
        }
        (enabled && !s ? ui.start : ui.panel.querySelector('[data-voice-close]')).focus();
    }

    async function close() {
        if (s) await end('user_ended');
        ui.panel.hidden = true;
        ui.launcher.hidden = false;
        ui.launcher.setAttribute('aria-expanded', 'false');
        ui.launcher.focus();
    }

    // ---- starting --------------------------------------------------------------------------------

    function supported() {
        return Boolean(win.WebSocket && win.AudioContext && win.AudioWorkletNode && win.navigator.mediaDevices?.getUserMedia);
    }

    async function start() {
        if (!supported()) {
            showMessage('Your browser does not support voice. Try a recent Chrome, Edge, Safari or Firefox, or use the text chat.');
            return;
        }

        const mode = root.querySelector('input[name="voice-mode"]:checked').value;
        ui.start.disabled = true;

        // Both must be created inside the tap: mobile browsers only allow audio and the mic from a user gesture.
        const context = new win.AudioContext();
        context.resume();
        const micRequest = win.navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
        });
        const deviceClass = win.matchMedia('(max-width: 639px)').matches ? 'mobile' : 'desktop';

        s = {
            mode, context, stream: null, connection: null, uuid: null, token: null, wsUrl: null, model: null,
            handle: null, reconnects: 0, startedAt: 0, maxSeconds: 0, ending: false, holding: false,
            prebuffer: [], cancelled: new Set(), usage: { input: 0, output: 0 },
            batcher: new TranscriptBatcher(), captionLines: { user: null, assistant: null }, timers: [],
            playback: new Playback(context),
        };
        ui.captions.replaceChildren();
        show('live');
        setStatus('Starting…');

        const [mic, session] = await Promise.allSettled([micRequest, api.start(mode, deviceClass)]);

        if (session.status === 'rejected' || session.value.kind !== 'ok') {
            if (mic.status === 'fulfilled') mic.value.getTracks().forEach((t) => t.stop());
            const res = session.value ?? { kind: 'network' };
            await teardown();
            showMessage(startFailureText(res));
            return;
        }

        Object.assign(s, {
            uuid: session.value.data.conversation_uuid,
            token: session.value.data.token,
            wsUrl: session.value.data.ws_url,
            model: session.value.data.model,
            maxSeconds: session.value.data.max_seconds,
        });

        if (mic.status === 'rejected') {
            const denied = mic.reason?.name === 'NotAllowedError' || mic.reason?.name === 'SecurityError';
            await end(denied ? 'mic_denied' : 'error', denied
                ? 'Microphone access is blocked. Allow the microphone for this site in your browser settings, or use the text chat.'
                : 'No microphone was found. Connect one, or use the text chat.');
            return;
        }

        s.stream = mic.value;

        try {
            await startCapture(root.dataset.workletUrl);
            setStatus('Connecting…');
            await connect();
        } catch (failure) {
            const tokenProblem = isTokenProblem(failure?.reason);
            await end(tokenProblem ? 'token_expired' : 'error', tokenProblem
                ? 'The voice session expired before it started. Please start again.'
                : 'Could not connect to the voice service. Check your connection and try again.');
            return;
        }

        s.startedAt = Date.now();
        s.timers.push(win.setInterval(tick, 1000));
        s.timers.push(win.setInterval(flushTranscripts, TRANSCRIPT_FLUSH_MS));
        ui.hold.hidden = mode !== 'hold_to_talk';
        ui.meter.hidden = mode !== 'hands_free';
        setStatus(mode === 'hold_to_talk' ? 'Hold the button and speak.' : 'Listening… just talk.');
        (mode === 'hold_to_talk' ? ui.hold : ui.panel.querySelector('[data-voice-end]')).focus();
    }

    function startFailureText(res) {
        if (res.kind === 'access_required') return 'Enter the access code in the text chat first, then try voice again.';
        if (res.kind === 'rate_limited') return errorMessage(res.data) ?? 'Too many voice sessions. Please wait a few minutes.';
        if (res.kind === 'unavailable') return errorMessage(res.data) ?? 'Voice is not available right now.';
        if (res.kind === 'network') return 'Could not reach the shop. Check your connection and try again.';
        if (res.kind === 'expired') return 'Your session has expired. Reload the page and try again.';
        return 'Voice could not start. Please try again.';
    }

    async function startCapture(workletUrl) {
        await s.context.audioWorklet.addModule(workletUrl);
        const source = s.context.createMediaStreamSource(s.stream);
        const node = new win.AudioWorkletNode(s.context, 'stride-capture');
        // Chrome only runs a worklet that is pulled by the destination: connect it through a silent gain.
        const silent = s.context.createGain();
        silent.gain.value = 0;
        source.connect(node).connect(silent).connect(s.context.destination);

        const resampler = new Resampler(s.context.sampleRate, INPUT_RATE);
        node.port.onmessage = (event) => onMicBlock(new Float32Array(event.data), resampler);
        s.captureNodes = [source, node, silent];
    }

    function onMicBlock(block, resampler) {
        if (!s || s.ending) return;
        ui.meter.style.setProperty('--level', Math.min(0.4, rms(block) * 4).toFixed(2));
        const chunk = bytesToBase64(floatToPcm16Bytes(resampler.process(block)));

        if (s.mode === 'hands_free') {
            s.connection?.sendAudio(chunk);
        } else if (s.holding) {
            s.connection?.sendAudio(chunk);
        } else {
            // Not speaking: keep a short rolling pre-buffer, because manual VAD skips the server's own.
            s.prebuffer.push(chunk);
            if (s.prebuffer.length > PREBUFFER_CHUNKS) s.prebuffer.shift();
        }
    }

    // ---- the Live connection -------------------------------------------------------------------

    async function connect() {
        const connection = new LiveConnection({
            url: s.wsUrl, token: s.token, model: s.model, handle: s.handle,
            handlers: {
                audio: (data) => {
                    s.playback.enqueue(data);
                    if (s.mode === 'hands_free') setStatus('Speaking… (talk to interrupt)');
                },
                inputText: (text) => { s.batcher.add('user', text); updateCaptions(); },
                outputText: (text) => { s.batcher.add('assistant', text); updateCaptions(); },
                interrupted: () => { s.playback.flush(); endTurn(); },
                turnComplete: () => {
                    endTurn();
                    flushTranscripts();
                    if (s.mode === 'hands_free') setStatus('Listening…');
                },
                toolCall: (calls) => runTools(calls),
                toolCancel: (ids) => ids.forEach((id) => s.cancelled.add(id)),
                goAway: () => reconnect('goAway'),
                handle: (handle) => { s.handle = handle; },
                usage: (meta) => {
                    // Client-reported and approximate: the prompt count is the running context size.
                    s.usage.input = Math.max(s.usage.input, meta.promptTokenCount ?? 0);
                    s.usage.output += (meta.responseTokenCount ?? 0) + (meta.thoughtsTokenCount ?? 0);
                },
                close: (info) => {
                    if (!info.byUs && s && !s.ending && connection === s.connection) reconnect('closed', info);
                },
            },
        });
        s.connection = connection;
        await connection.open();
    }

    function endTurn() {
        s.batcher.endTurn();
        s.captionLines = { user: null, assistant: null };
    }

    async function reconnect(why, info = null) {
        if (!s || s.ending) return;

        if (!s.handle || s.reconnects >= MAX_RECONNECTS) {
            const tokenProblem = isTokenProblem(info?.reason);
            await end(why === 'goAway' ? 'go_away_failed' : (tokenProblem ? 'token_expired' : 'error'), tokenProblem
                ? 'The voice session expired. Start again to keep talking.'
                : 'The connection to the voice service was lost. Start again to keep talking.');
            return;
        }

        s.reconnects++;
        setStatus('Reconnecting…');
        s.playback.flush();
        const old = s.connection;
        old?.close();

        try {
            // Resuming does not use up the single-use token (verified 8 Nov 2026).
            await connect();
        } catch (failure) {
            if (!isTokenProblem(failure?.reason)) return reconnect(why, failure);
            // The token itself has expired: ask Laravel for a new one and resume with the same handle.
            const fresh = await api.token(s.uuid);
            if (fresh.kind !== 'ok') return reconnect(why, { reason: 'token expired' });
            s.token = fresh.data.token;
            try {
                await connect();
            } catch (again) {
                return reconnect(why, again);
            }
        }
        setStatus(s.mode === 'hold_to_talk' ? 'Hold the button and speak.' : 'Listening…');
    }

    async function runTools(calls) {
        const responses = [];
        for (const call of calls) {
            const res = await api.toolCall(s.uuid, call);
            if (res.data?.cart) updateCartBadge(doc, res.data.cart.count);
            responses.push({
                id: call.id,
                name: call.name,
                response: res.kind === 'ok' ? res.data.response : { error: 'The shop could not run this right now.' },
            });
        }
        // Calls the customer interrupted are not answered (toolCallCancellation).
        const live = responses.filter((r) => !s.cancelled.has(r.id));
        if (live.length) s.connection?.toolResponse(live);
    }

    // ---- hold to talk ------------------------------------------------------------------------------

    function pressHold(event) {
        if (!s || s.mode !== 'hold_to_talk' || s.holding || !s.connection?.ready) return;
        event?.preventDefault();
        s.holding = true;
        ui.hold.setAttribute('data-active', '');
        // Barge-in by gesture: stop the model's speech first.
        s.playback.flush();
        s.connection.activityStart();
        for (const chunk of s.prebuffer.splice(0)) s.connection.sendAudio(chunk);
        setStatus('Listening… release when you are done.');
    }

    function releaseHold() {
        if (!s?.holding) return;
        s.holding = false;
        ui.hold.removeAttribute('data-active');
        s.connection?.activityEnd();
        setStatus('Thinking…');
    }

    // ---- timing, transcripts, ending ---------------------------------------------------------------

    function elapsed() {
        return s?.startedAt ? Math.round((Date.now() - s.startedAt) / 1000) : 0;
    }

    function tick() {
        const left = s.maxSeconds - elapsed();
        if (left <= 0) {
            end('max_duration', 'This voice session reached its time limit. Start again to keep talking.');
            return;
        }
        ui.timer.classList.toggle('hidden', left > 60);
        ui.timer.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')} left`;
    }

    function flushTranscripts() {
        if (!s?.uuid) return;
        const segments = s.batcher.take();
        if (segments.length) api.transcripts(s.uuid, segments);
    }

    async function teardown() {
        if (!s) return;
        s.timers.forEach((t) => win.clearInterval(t));
        s.connection?.close();
        s.playback?.flush();
        // Stopping the tracks removes the browser's microphone indicator.
        s.stream?.getTracks().forEach((t) => t.stop());
        s.captureNodes?.forEach((n) => n.disconnect());
        try {
            await s.context.close();
        } catch {
            // Already closed.
        }
        ui.start.disabled = false;
        ui.hold.hidden = true;
        ui.meter.hidden = true;
        s = null;
    }

    async function end(reason, message = null) {
        if (!s || s.ending) return;
        s.ending = true;
        const current = s;
        releaseHold();

        if (current.uuid) {
            const segments = current.batcher.takeAll();
            if (segments.length) await api.transcripts(current.uuid, segments);
            await api.end(current.uuid, {
                reason,
                duration_seconds: elapsed(),
                usage: { input_tokens: current.usage.input, output_tokens: current.usage.output },
            });
        }

        await teardown();
        showMessage(message ?? 'Conversation ended. Thanks for talking to Stride!');
    }

    // Leaving the page or locking the phone ends the session (AC-V10). sendBeacon survives page unload.
    function endOnLeave() {
        if (!s?.uuid || s.ending) return;
        const body = new FormData();
        body.append('_token', csrfToken(doc) ?? '');
        body.append('reason', 'backgrounded');
        body.append('duration_seconds', String(elapsed()));
        const segments = s.batcher.takeAll();
        if (segments.length) api.transcripts(s.uuid, segments);
        win.navigator.sendBeacon?.(api.endUrl(s.uuid), body);
        s.uuid = null;
        end('backgrounded', 'The voice session ended because the page was hidden.');
    }

    // ---- events ------------------------------------------------------------------------------------

    ui.launcher.addEventListener('click', open);
    ui.panel.querySelector('[data-voice-close]').addEventListener('click', close);
    ui.panel.querySelector('[data-voice-end]').addEventListener('click', () => end('user_ended'));
    ui.start.addEventListener('click', start);
    root.querySelector('[data-voice-again]').addEventListener('click', () => show(enabled ? 'intro' : 'message'));

    ui.hold.addEventListener('pointerdown', (e) => {
        ui.hold.setPointerCapture?.(e.pointerId);
        pressHold(e);
    });
    for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) ui.hold.addEventListener(type, releaseHold);
    ui.hold.addEventListener('contextmenu', (e) => e.preventDefault());
    // Keyboard: hold Space or Enter.
    ui.hold.addEventListener('keydown', (e) => {
        if ((e.key === ' ' || e.key === 'Enter') && !e.repeat) pressHold(e);
    });
    ui.hold.addEventListener('keyup', (e) => {
        if (e.key === ' ' || e.key === 'Enter') releaseHold();
    });

    ui.panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });

    doc.addEventListener('visibilitychange', () => {
        if (doc.visibilityState === 'hidden') endOnLeave();
    });
    win.addEventListener('pagehide', endOnLeave);
    win.addEventListener('offline', () => s && setStatus('You are offline. Waiting for the connection…'));
}
