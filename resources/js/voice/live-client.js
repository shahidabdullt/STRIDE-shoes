import { INPUT_RATE } from './pcm.js';

/**
 * Gemini Live API WebSocket protocol (BLUEPRINT §3.2, verified in Phase 0 and on 8 Nov 2026).
 * The browser only ever holds a single-use ephemeral token whose setup is locked server-side; the only
 * field it may send is `sessionResumption` (to resume with a handle).
 */

/**
 * Dispatches one server message to handlers. Unknown message types (e.g. `voiceActivity`, seen in Phase 0
 * but undocumented) are ignored, never fatal.
 */
export function routeMessage(message, h) {
    if (!message || typeof message !== 'object') return;

    if (message.setupComplete) h.setupComplete?.();

    const content = message.serverContent;
    if (content) {
        for (const part of content.modelTurn?.parts ?? []) {
            if (part.inlineData?.data) h.audio?.(part.inlineData.data);
        }
        if (content.inputTranscription?.text) h.inputText?.(content.inputTranscription.text);
        if (content.outputTranscription?.text) h.outputText?.(content.outputTranscription.text);
        if (content.interrupted) h.interrupted?.();
        if (content.turnComplete) h.turnComplete?.();
    }

    if (message.toolCall?.functionCalls?.length) h.toolCall?.(message.toolCall.functionCalls);
    if (message.toolCallCancellation?.ids?.length) h.toolCancel?.(message.toolCallCancellation.ids);
    if (message.goAway) h.goAway?.(message.goAway.timeLeft);

    const update = message.sessionResumptionUpdate;
    if (update?.resumable && update.newHandle) h.handle?.(update.newHandle);

    if (message.usageMetadata) h.usage?.(message.usageMetadata);
}

/** Frames arrive as text or as binary (Blob in browsers) holding JSON. */
export async function parseFrame(data) {
    try {
        if (typeof data === 'string') return JSON.parse(data);
        if (data && typeof data.text === 'function') return JSON.parse(await data.text());
        if (data instanceof ArrayBuffer) return JSON.parse(new TextDecoder().decode(data));
    } catch {
        // Not JSON: ignore.
    }
    return null;
}

/** A token problem (expired, used up) as reported in the close reason, as opposed to a network failure. */
export function isTokenProblem(reason) {
    return /token|expired|used too many|unauthenticated|permission/i.test(reason ?? '');
}

export class LiveConnection {
    constructor({ url, token, model, handle = null, handlers, WebSocketImpl = globalThis.WebSocket }) {
        this.url = `${url}?access_token=${encodeURIComponent(token)}`;
        this.model = model;
        this.handle = handle;
        this.handlers = handlers;
        this.WebSocketImpl = WebSocketImpl;
        this.ws = null;
        this.ready = false;
        this.closedByUs = false;
    }

    /** Resolves on setupComplete; rejects with {code, reason} if the socket closes before that. */
    open() {
        return new Promise((resolve, reject) => {
            const ws = new this.WebSocketImpl(this.url);
            this.ws = ws;

            ws.onopen = () => {
                const setup = { model: this.model, sessionResumption: this.handle ? { handle: this.handle } : {} };
                ws.send(JSON.stringify({ setup }));
            };

            ws.onmessage = async (event) => {
                const message = await parseFrame(event.data);
                routeMessage(message, {
                    ...this.handlers,
                    setupComplete: () => {
                        this.ready = true;
                        resolve();
                    },
                });
            };

            ws.onclose = (event) => {
                const wasReady = this.ready;
                this.ready = false;
                if (!wasReady) reject({ code: event.code, reason: event.reason });
                else this.handlers.close?.({ code: event.code, reason: event.reason, byUs: this.closedByUs });
            };
        });
    }

    send(message) {
        if (this.ws?.readyState === 1 && this.ready) this.ws.send(JSON.stringify(message));
    }

    sendAudio(base64) {
        this.send({ realtimeInput: { audio: { data: base64, mimeType: `audio/pcm;rate=${INPUT_RATE}` } } });
    }

    activityStart() {
        this.send({ realtimeInput: { activityStart: {} } });
    }

    activityEnd() {
        this.send({ realtimeInput: { activityEnd: {} } });
    }

    audioStreamEnd() {
        this.send({ realtimeInput: { audioStreamEnd: true } });
    }

    toolResponse(functionResponses) {
        this.send({ toolResponse: { functionResponses } });
    }

    close() {
        this.closedByUs = true;
        try {
            this.ws?.close(1000, 'client ended');
        } catch {
            // Already closed.
        }
    }
}
