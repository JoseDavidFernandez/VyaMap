import './bootstrap';
import 'leaflet/dist/leaflet.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';

const pages = import.meta.glob<{
    default: DefineComponent;
}>('./pages/**/*.vue', {
    eager: true,
});

createInertiaApp({
    title: (title) => (title ? `${title} · VyaMap` : 'VyaMap'),

    // La barra de progreso no inyecta su <style>: la CSP no admite estilos en
    // línea sin hash. Su CSS está en app.css («Barra de progreso de Inertia»).
    progress: {
        includeCSS: false,
    },

    resolve: (name) => {
        const page = pages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page;
    },

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .mount(el);
    },
});