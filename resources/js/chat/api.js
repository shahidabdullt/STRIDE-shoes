/**
 * The text assistant's JSON endpoints (BLUEPRINT §7.2). Every call resolves (never throws) to
 * { kind, status, data }, where kind is one of:
 *   ok, access_required, not_found, ended, invalid, rate_limited, unavailable, expired, network, error.
 * The widget decides what to show from `kind`, so all status handling lives in one tested place.
 */
export function classify(status, data) {
    if (status >= 200 && status < 300) return 'ok';
    if (status === 403 && data?.error === 'access_code_required') return 'access_required';
    if (status === 404) return 'not_found';
    if (status === 409) return 'ended';
    if (status === 419) return 'expired';
    if (status === 422) return 'invalid';
    if (status === 429) return 'rate_limited';
    if (status === 503) return 'unavailable';
    return 'error';
}

/** The first human-readable message in an error body, if any. */
export function errorMessage(data) {
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) return String(first[0]);
    }
    return typeof data?.message === 'string' ? data.message : null;
}

export function createChatApi({ fetchImpl = globalThis.fetch, headers = () => ({}), base = '/assistant' } = {}) {
    async function request(method, path, body) {
        let response;
        try {
            response = await fetchImpl(base + path, {
                method,
                headers: headers(),
                credentials: 'same-origin',
                body: body === undefined ? undefined : JSON.stringify(body),
            });
        } catch {
            return { kind: 'network', status: 0, data: null };
        }

        const data = response.status === 204 ? null : await response.json().catch(() => null);
        return { kind: classify(response.status, data), status: response.status, data };
    }

    const conversation = (uuid) => `/conversations/${encodeURIComponent(uuid)}`;

    return {
        start: (deviceClass) => request('POST', '/conversations', { device_class: deviceClass }),
        load: (uuid) => request('GET', conversation(uuid)),
        send: (uuid, message) => request('POST', `${conversation(uuid)}/messages`, { message }),
        end: (uuid) => request('POST', `${conversation(uuid)}/end`, {}),
        access: (code) => request('POST', '/access', { code }),
    };
}
