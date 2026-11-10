/**
 * Reads the CSRF token Laravel renders into <meta name="csrf-token">.
 * JSON requests from the chat and voice modules send it as the X-CSRF-TOKEN header.
 */
export function csrfToken(doc = document) {
    return doc.querySelector('meta[name="csrf-token"]')?.content ?? null;
}

export function jsonHeaders(doc = document) {
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    const token = csrfToken(doc);

    if (token) {
        headers['X-CSRF-TOKEN'] = token;
    }

    return headers;
}
