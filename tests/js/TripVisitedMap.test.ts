import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

/*
 * Al cambiar el estado del viaje cambia la fase del mapa. Vue no debe
 * reescribir el className del contenedor de Leaflet: perdería sus clases
 * (leaflet-container…) y el mapa se quedaría en negro hasta recargar.
 */

// Leaflet simulado: L.map() marca su contenedor como hace el real y el resto
// de llamadas devuelven un objeto encadenable.
vi.mock('leaflet', () => {
    const chain: unknown = new Proxy(function () {}, {
        get: (_target, property) => (property === 'then' ? undefined : chain),
        apply: () => chain,
    });

    const leaflet = new Proxy({}, {
        get: (_target, property) => (property === 'map'
            ? (element: HTMLElement) => {
                element.classList.add('leaflet-container', 'leaflet-touch');

                return chain;
            }
            : chain),
    });

    return { default: leaflet };
});

describe('TripVisitedMap', () => {
    it('keeps the Leaflet container intact when the trip phase changes', async () => {
        const { default: TripVisitedMap } = await import('../../resources/js/components/trips/TripVisitedMap.vue');

        const wrapper = mount(TripVisitedMap, { props: { phase: 'upcoming', visits: [] } });
        await vi.dynamicImportSettled();

        const container = wrapper.get('.leaflet-container');
        expect(wrapper.classes()).toContain('visited-map--planned');

        for (const phase of ['completed', 'cancelled', 'upcoming'] as const) {
            await wrapper.setProps({ phase });

            expect(container.classes()).toEqual(expect.arrayContaining(['leaflet-container', 'leaflet-touch', 'vyamap-leaflet-dark']));
        }

        expect(wrapper.classes()).toContain('visited-map--planned');
        expect(wrapper.text()).toContain('Planned destinations');
    });
});
