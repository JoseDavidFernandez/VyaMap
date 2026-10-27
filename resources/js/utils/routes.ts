/**
 * URLs legibles de las páginas (ver Trip::getRouteKey y Country::getRouteKey).
 *
 * Viajes: /trips/386-new-york. El id identifica el viaje; el slug solo se lee.
 * Si este slug no coincidiera con el del servidor (Str::slug translitera
 * algunos alfabetos de otra forma), la página redirige a la canónica.
 * Países: /countries/italy, con el slug que envía el servidor.
 * Las peticiones que modifican datos usan el id, que no depende del nombre.
 */
export const slugify = (text: string): string =>
    text
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/@/g, '-at-')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s-]+/g, '-')
        .replace(/^-+|-+$/g, '');

export const tripKey = (trip: { id: number; name: string }): string => {
    const slug = slugify(trip.name);

    return slug ? `${trip.id}-${slug}` : String(trip.id);
};

export const tripPath = (trip: { id: number; name: string }, page: '' | 'edit' | 'photos' = ''): string =>
    `/trips/${tripKey(trip)}${page ? `/${page}` : ''}`;

export const countryPath = (country: { slug: string }): string => `/countries/${country.slug}`;
