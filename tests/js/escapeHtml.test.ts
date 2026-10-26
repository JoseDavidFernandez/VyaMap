import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { escapeHtml } from '../../resources/js/utils/escapeHtml';

/*
 * Leaflet interpreta como HTML el texto de bindTooltip. Los datos que llegan
 * del servidor (números de vuelo, ciudades…) se escapan antes de llegar a un
 * tooltip para que no puedan inyectar HTML ni scripts.
 */

// Leaflet simulado: cualquier llamada devuelve el mismo objeto encadenable y
// bindTooltip guarda el contenido recibido.
const tooltips: string[] = [];

vi.mock('leaflet', () => {
    const chain: unknown = new Proxy(function () {}, {
        get: (_target, property) => {
            if (property === 'then') {
                return undefined;
            }

            if (property === 'bindTooltip') {
                return (content: string) => {
                    tooltips.push(content);

                    return chain;
                };
            }

            return chain;
        },
        apply: () => chain,
    });

    return { default: chain };
});

describe('escapeHtml', () => {
    it('escapes every HTML special character', () => {
        expect(escapeHtml(`<img src=x onerror="alert('x')"> & co`)).toBe(
            '&lt;img src=x onerror=&quot;alert(&#039;x&#039;)&quot;&gt; &amp; co',
        );
    });

    it('turns empty values into an empty string and keeps plain text', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
        expect(escapeHtml('Madrid')).toBe('Madrid');
        expect(escapeHtml(42)).toBe('42');
    });
});

describe('TravelMap tooltips', () => {
    it('escape flight numbers and city names', async () => {
        const { default: TravelMap } = await import('../../resources/js/components/dashboard/TravelMap.vue');
        const point = (city: string, latitude: number, longitude: number) => ({ name: 'Airport', city, latitude, longitude });

        mount(TravelMap, {
            props: {
                flights: [{
                    id: 1,
                    status: 'flown',
                    flight_number: '<IMG SRC=X ONERROR=Y>',
                    airline: null,
                    departure: null,
                    arrival: null,
                    origin: point('<b>Madrid</b>', 40.47, -3.56),
                    destination: point('Barcelona', 41.29, 2.07),
                }],
            },
            attachTo: document.body,
        });

        const html = tooltips.join('\n');

        expect(tooltips.length).toBeGreaterThan(0);
        expect(html).not.toContain('<IMG');
        expect(html).not.toContain('<b>');
        expect(html).toContain('&lt;IMG SRC=X ONERROR=Y&gt;');
        expect(html).toContain('&lt;b&gt;Madrid&lt;/b&gt;');
    });
});
