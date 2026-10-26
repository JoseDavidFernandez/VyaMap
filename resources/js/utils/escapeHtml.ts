/*
 * Escapa texto para insertarlo en HTML. Leaflet interpreta como HTML el texto
 * de bindTooltip/bindPopup: todo dato que venga del servidor (ciudades,
 * códigos, números de vuelo, aerolíneas) pasa por aquí antes de llegar a un
 * tooltip.
 */
export const escapeHtml = (value: string | number | null | undefined): string =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
