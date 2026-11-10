/**
 * Joins the Live API's small transcription fragments ("Stride here. I ", "am the voice", …) into one
 * segment per speaker per turn, and hands finished segments out in batches for
 * POST /voice/sessions/{uuid}/transcripts (max 50 segments, 2000 characters each).
 *
 * Input and output transcripts have no guaranteed order relative to each other, so each speaker has its
 * own buffer; a turn boundary (turnComplete or interrupted) closes the customer's segment first.
 */
export const MAX_SEGMENT_CHARS = 2000;

export class TranscriptBatcher {
    constructor({ now = () => new Date() } = {}) {
        this.now = now;
        this.current = { user: null, assistant: null };
        this.ready = [];
    }

    add(role, text) {
        if (!text) return;
        const current = this.current[role];
        if (current) current.text += text;
        else this.current[role] = { role, text, at: this.now().toISOString() };
    }

    /** The text being spoken right now by `role`, for live captions. */
    live(role) {
        return this.current[role]?.text ?? '';
    }

    endTurn() {
        for (const role of ['user', 'assistant']) {
            const segment = this.current[role];
            this.current[role] = null;
            if (!segment) continue;
            const text = segment.text.replace(/\s+/g, ' ').trim();
            for (let i = 0; i < text.length; i += MAX_SEGMENT_CHARS) {
                this.ready.push({ role, text: text.slice(i, i + MAX_SEGMENT_CHARS), at: segment.at });
            }
        }
    }

    /** Finished segments, at most 50, removed from the queue. */
    take() {
        return this.ready.splice(0, 50);
    }

    /** Everything, including unfinished speech: used when the session ends. */
    takeAll() {
        this.endTurn();
        const all = [];
        while (this.ready.length) all.push(...this.take());
        return all;
    }
}
