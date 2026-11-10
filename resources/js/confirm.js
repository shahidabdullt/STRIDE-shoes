/**
 * Forms with a data-confirm="message" attribute ask before submitting (used for admin deletes).
 */
export function confirmMessage(form) {
    return form?.dataset?.confirm ?? null;
}

export function initConfirmForms(doc = document, ask = (message) => window.confirm(message)) {
    doc.addEventListener('submit', (event) => {
        const message = confirmMessage(event.target);

        if (message && !ask(message)) {
            event.preventDefault();
        }
    });
}
