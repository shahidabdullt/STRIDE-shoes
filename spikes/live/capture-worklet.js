// Spike S2 capture processor: mono Float32 at the context rate -> PCM16 LE chunks.
// processorOptions.targetRate: 16000 to downsample in the worklet, or 0 to keep the native rate.

class CaptureProcessor extends AudioWorkletProcessor {
    constructor(options) {
        super();
        const target = options.processorOptions?.targetRate || 0;
        this.outRate = target > 0 && target < sampleRate ? target : sampleRate;
        this.step = sampleRate / this.outRate;
        this.pos = 0;
        this.prev = 0;
        // Store the size: a buffer that was transferred to the main thread has length 0.
        this.chunkSize = Math.round(this.outRate * 0.04); // 40 ms
        this.chunk = new Int16Array(this.chunkSize);
        this.fill = 0;
        this.port.postMessage({ type: 'rate', rate: this.outRate, contextRate: sampleRate });
    }

    process(inputs) {
        const input = inputs[0]?.[0];
        if (!input) return true;

        let sumSq = 0;
        for (let i = 0; i < input.length; i++) sumSq += input[i] * input[i];

        // Linear interpolation over the virtual signal [prev, ...input], where index 0
        // is the last sample of the previous block, so block boundaries interpolate correctly.
        const at = (k) => (k === 0 ? this.prev : input[k - 1]);
        while (this.pos < input.length) {
            const i = Math.floor(this.pos);
            const frac = this.pos - i;
            const a = at(i);
            const s = Math.max(-1, Math.min(1, a + (at(i + 1) - a) * frac));
            this.chunk[this.fill++] = s < 0 ? s * 0x8000 : s * 0x7fff;

            if (this.fill === this.chunkSize) {
                this.port.postMessage({ type: 'pcm', buffer: this.chunk.buffer }, [this.chunk.buffer]);
                this.chunk = new Int16Array(this.chunkSize);
                this.fill = 0;
            }
            this.pos += this.step;
        }
        this.pos -= input.length;
        this.prev = input[input.length - 1];

        this.port.postMessage({ type: 'level', rms: Math.sqrt(sumSq / input.length) });
        return true;
    }
}

registerProcessor('capture-processor', CaptureProcessor);
