// AudioWorklet: collects the microphone's mono float samples into 40 ms blocks at the context rate and
// posts them to the main thread, which resamples and encodes them (resources/js/voice/pcm.js).
// Self-contained on purpose: it is loaded by URL with audioWorklet.addModule().

class StrideCaptureProcessor extends AudioWorkletProcessor {
    constructor() {
        super();
        // Keep the size in a field: a buffer transferred with postMessage has length 0 afterwards (Phase 0 lesson).
        this.size = Math.round(sampleRate * 0.04);
        this.block = new Float32Array(this.size);
        this.fill = 0;
    }

    process(inputs) {
        const channel = inputs[0]?.[0];
        if (!channel) return true;

        for (let i = 0; i < channel.length; i++) {
            this.block[this.fill++] = channel[i];
            if (this.fill === this.size) {
                this.port.postMessage(this.block.buffer, [this.block.buffer]);
                this.block = new Float32Array(this.size);
                this.fill = 0;
            }
        }
        return true;
    }
}

registerProcessor('stride-capture', StrideCaptureProcessor);
