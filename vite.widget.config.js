import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    publicDir: false,
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
        __VUE_OPTIONS_API__: false,
        __VUE_PROD_DEVTOOLS__: false,
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
    },
    build: {
        outDir: 'public',
        emptyOutDir: false,
        lib: {
            entry: 'resources/widget/main.js',
            name: 'DocsChatWidget',
            formats: ['iife'],
            fileName: () => 'widget.js',
        },
    },
});
