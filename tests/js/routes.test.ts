import { describe, expect, it } from 'vitest';
import { countryPath, slugify, tripKey, tripPath } from '../../resources/js/utils/routes';

/**
 * URLs legibles (resources/js/utils/routes.ts). Los valores esperados son la
 * salida real de Str::slug de Laravel, que genera las URL canónicas.
 */
describe('slugify', () => {
    it.each([
        ['New York', 'new-york'],
        ['Rome & Florence', 'rome-florence'],
        ['New Year in London', 'new-year-in-london'],
        ['São Paulo', 'sao-paulo'],
        ["Côte d'Ivoire", 'cote-divoire'],
        ['Åland Islands', 'aland-islands'],
        ['Bosnia and Herzegovina', 'bosnia-and-herzegovina'],
        ['Ruta: Zürich → München', 'ruta-zurich-munchen'],
        ['Trip @ the coast', 'trip-at-the-coast'],
        ['  Spaces   and---dashes  ', 'spaces-and-dashes'],
        ['🏝️', ''],
    ])('%s → %s, igual que Str::slug', (name, slug) => {
        expect(slugify(name)).toBe(slug);
    });

    it('no translitera alfabetos no latinos: el servidor redirige a la canónica', () => {
        // Str::slug('Ελλάδα') es "ellada"; aquí queda vacío y la clave es solo el id.
        expect(slugify('Ελλάδα')).toBe('');
        expect(tripKey({ id: 7, name: 'Ελλάδα' })).toBe('7');
    });
});

describe('rutas', () => {
    it('construye las URL de las páginas de un viaje', () => {
        const trip = { id: 386, name: 'New York' };

        expect(tripPath(trip)).toBe('/trips/386-new-york');
        expect(tripPath(trip, 'edit')).toBe('/trips/386-new-york/edit');
        expect(tripPath(trip, 'photos')).toBe('/trips/386-new-york/photos');
    });

    it('usa solo el id si el nombre no da slug', () => {
        expect(tripPath({ id: 12, name: '🏝️' })).toBe('/trips/12');
    });

    it('usa el slug que envía el servidor para los países', () => {
        expect(countryPath({ slug: 'cote-divoire' })).toBe('/countries/cote-divoire');
    });
});
