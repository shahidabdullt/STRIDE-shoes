import { OUTPUT_RATE, base64ToBytes, pcm16BytesToFloat } from './pcm.js';

/**
 * Gapless playback of the model's 24 kHz PCM chunks. flush() stops everything at once, for barge-in
 * (`serverContent.interrupted`, or pressing hold-to-talk while the model speaks).
 */
export class Playback {
    constructor(context) {
        this.context = context;
        this.nextTime = 0;
        this.sources = new Set();
    }

    get playing() {
        return this.sources.size > 0;
    }

    enqueue(base64) {
        const samples = pcm16BytesToFloat(base64ToBytes(base64));
        if (samples.length === 0) return;

        // The context may run at 44.1 or 48 kHz; a 24 kHz buffer is resampled by the browser.
        const buffer = this.context.createBuffer(1, samples.length, OUTPUT_RATE);
        buffer.copyToChannel(samples, 0);

        const source = this.context.createBufferSource();
        source.buffer = buffer;
        source.connect(this.context.destination);

        const startAt = Math.max(this.context.currentTime + 0.02, this.nextTime);
        source.start(startAt);
        this.nextTime = startAt + buffer.duration;

        this.sources.add(source);
        source.onended = () => this.sources.delete(source);
    }

    flush() {
        for (const source of this.sources) {
            try {
                source.stop();
            } catch {
                // Already stopped.
            }
        }
        this.sources.clear();
        this.nextTime = 0;
    }
}
