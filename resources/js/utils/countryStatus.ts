/*
 * Recuento de países y territorios a partir de la clasificación del servidor
 * (App\Support\SovereignCountries::classify):
 *
 * - Países: Estados soberanos (miembros de la ONU), sobre 193.
 * - Territorios: territorios dependientes y casos pendientes de decisión.
 * - Códigos especiales (desconocido, zonas sin Estado): no cuentan en ninguno.
 */

export type CountryStatus = 'sovereign' | 'territory' | 'pending_decision' | 'special';

interface Classified {
    iso_code: string;
    country_status: CountryStatus;
}

export const isTerritoryStatus = (status: CountryStatus) =>
    status === 'territory' || status === 'pending_decision';

export const countCountries = (items: Classified[]) => {
    const sovereign = new Set<string>();
    const territories = new Set<string>();

    items.forEach((item) => {
        if (item.country_status === 'sovereign') {
            sovereign.add(item.iso_code);
        } else if (isTerritoryStatus(item.country_status)) {
            territories.add(item.iso_code);
        }
    });

    return { countries: sovereign.size, territories: territories.size };
};
