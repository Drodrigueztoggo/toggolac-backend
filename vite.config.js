import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { compression } from 'vite-plugin-compression2'; // npm install vite-plugin-compression2 -D

export default defineConfig(({ command }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: command === 'serve', // Only refresh in dev, not prod build
        }),
        // Generates .gz and .br files alongside assets
        compression({ algorithm: 'brotliCompress' }),
        compression({ algorithm: 'gzip' }),
    ],

    build: {
        // Warn when any chunk exceeds 300KB (forces you to act)
        chunkSizeWarningLimit: 300,

        rollupOptions: {
            output: {
                // Split vendor libraries into separate cached chunks
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        // Lodash gets its own chunk (it's heavy)
                        if (id.includes('lodash')) return 'vendor-lodash';
                        // Axios gets its own chunk
                        if (id.includes('axios')) return 'vendor-axios';
                        // Everything else from node_modules
                        return 'vendor';
                    }
                },
                // Deterministic file names with content hash for cache busting
                chunkFileNames: 'js/[name]-[hash].js',
                entryFileNames: 'js/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },

        // Generate source maps for production debugging (disable if bundle size is critical)
        sourcemap: false,

        // Target modern browsers — reduces polyfill weight significantly
        target: 'es2018',

        // Minify with esbuild (default, fast) or 'terser' for more aggressive minification
        minify: 'esbuild',
    },

    // Optimize dependency pre-bundling in dev
    optimizeDeps: {
        include: ['axios', 'lodash'],
    },
}));
