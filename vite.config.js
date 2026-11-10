import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // The AudioWorklet is its own entry: it is loaded by URL with audioWorklet.addModule().
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/voice/audio-capture.worklet.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
