/**
 * Audio format helpers for the Live API (BLUEPRINT §3.2): input is 16-bit PCM little-endian at 16 kHz,
 * output is 16-bit PCM little-endian at 24 kHz, both base64 encoded.
 */

export const INPUT_RATE = 16000;
export const OUTPUT_RATE = 24000;

/** Float samples in [-1, 1] to PCM16 little-endian bytes. */
export function floatToPcm16Bytes(samples) {
    const bytes = new Uint8Array(samples.length * 2);
    const view = new DataView(bytes.buffer);
    for (let i = 0; i < samples.length; i++) {
        const s = Math.max(-1, Math.min(1, samples[i]));
        view.setInt16(i * 2, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    }
    return bytes;
}

/** PCM16 little-endian bytes to float samples in [-1, 1). */
export function pcm16BytesToFloat(bytes) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const out = new Float32Array(Math.floor(bytes.byteLength / 2));
    for (let i = 0; i < out.length; i++) out[i] = view.getInt16(i * 2, true) / 0x8000;
    return out;
}

export function bytesToBase64(bytes) {
    let binary = '';
    // Chunked so large buffers do not overflow the argument limit of String.fromCharCode.
    for (let i = 0; i < bytes.length; i += 0x8000) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }
    return btoa(binary);
}

export function base64ToBytes(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
}

/**
 * Streaming linear-interpolation resampler (the microphone usually runs at 44.1 or 48 kHz).
 * Keeps the last sample of the previous block so block boundaries interpolate correctly.
 */
export class Resampler {
    constructor(inRate, outRate) {
        this.step = inRate / outRate;
        this.passthrough = inRate === outRate;
        this.pos = 0;
        this.prev = 0;
    }

    process(input) {
        if (this.passthrough) return Float32Array.from(input);

        const out = [];
        // Index 0 is the previous block's last sample, index k (k >= 1) is input[k - 1].
        const at = (k) => (k === 0 ? this.prev : input[k - 1]);
        while (this.pos < input.length) {
            const i = Math.floor(this.pos);
            const frac = this.pos - i;
            const a = at(i);
            out.push(a + (at(i + 1) - a) * frac);
            this.pos += this.step;
        }
        this.pos -= input.length;
        if (input.length) this.prev = input[input.length - 1];
        return Float32Array.from(out);
    }
}

/** Root mean square of a block, for the microphone level indicator. */
export function rms(samples) {
    let sum = 0;
    for (let i = 0; i < samples.length; i++) sum += samples[i] * samples[i];
    return samples.length ? Math.sqrt(sum / samples.length) : 0;
}
