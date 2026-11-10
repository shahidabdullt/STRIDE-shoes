import { classify } from '../chat/api.js';

/**
 * The voice JSON endpoints (BLUEPRINT §7.3). Like the chat API, every call resolves to { kind, status, data }.
 */
export function createVoiceApi({ fetchImpl = globalThis.fetch, headers = () => ({}), base = '/voice' } = {}) {
    async function request(path, body) {
        let response;
        try {
            response = await fetchImpl(base + path, {
                method: 'POST',
                headers: headers(),
                credentials: 'same-origin',
                body: JSON.stringify(body ?? {}),
            });
        } catch {
            return { kind: 'network', status: 0, data: null };
        }

        const data = response.status === 204 ? null : await response.json().catch(() => null);
        return { kind: classify(response.status, data), status: response.status, data };
    }

    const session = (uuid) => `/sessions/${encodeURIComponent(uuid)}`;

    return {
        start: (mode, deviceClass) => request('/sessions', { mode, device_class: deviceClass }),
        token: (uuid) => request(`${session(uuid)}/token`, { reason: 'reconnect' }),
        toolCall: (uuid, call) => request(`${session(uuid)}/tool-calls`, { call_id: call.id, name: call.name, args: call.args ?? {} }),
        transcripts: (uuid, segments) => request(`${session(uuid)}/transcripts`, { segments }),
        end: (uuid, payload) => request(`${session(uuid)}/end`, payload),
        endUrl: (uuid) => `${base}${session(uuid)}/end`,
    };
}
