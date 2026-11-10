/**
 * Teselas de OpenStreetMap para todos los mapas de Leaflet.
 *
 * Sin subdominios ({s}.tile…): OSM recomienda el host único, que ya sirve por
 * HTTP/2 y CDN. La CSP de producción solo permite este origen en img-src, así
 * que cualquier proveedor nuevo hay que añadirlo también allí.
 */
export const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

export const OSM_ATTRIBUTION = '&copy; OpenStreetMap contributors';
