import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const stub = (file) => fileURLToPath(new URL(`./resources/js/vendor-stubs/${file}`, import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        // three-globe importa el renderer WebGPU (capa de heatmaps) y h3-js
        // (capas hexagonales), que el globo del perfil no usa: unos 260 KB con
        // gzip. Se sustituyen por módulos que lanzan un error claro si se usan.
        // Revisar al actualizar three-globe (docs/architecture.md, «Globo del perfil»).
        alias: [
            { find: /^three\/webgpu$/, replacement: stub('three-webgpu.ts') },
            { find: /^three\/tsl$/, replacement: stub('three-tsl.ts') },
            { find: /^h3-js$/, replacement: stub('h3-js.ts') },
        ],
    },
    build: {
        outDir: 'public/build',
        rollupOptions: {
            output: {
                // Leaflet (y su CSS) en un chunk propio, con nombre reconocible y
                // que se mantiene en caché entre despliegues si no se actualiza.
                // Solo lo piden las páginas con mapas. Los helpers de CommonJS
                // van aparte: si no, Rollup los mete en el chunk de Leaflet (es
                // UMD) y el globo del perfil lo descargaría entero por ellos.
                manualChunks: (id) => {
                    if (id.includes('/node_modules/leaflet/')) {
                        return 'leaflet';
                    }

                    if (id.includes('commonjsHelpers')) {
                        return 'commonjsHelpers';
                    }

                    return undefined;
                },
            },
        },
    },
});
