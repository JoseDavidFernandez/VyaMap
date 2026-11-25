import type { FlightRecord } from '../types/flights';

/*
 * Vuelos independientes del usuario (App\Http\Controllers\TripFlightController):
 * sugerencias por fechas (±1 día) y búsqueda. Solo lectura: asociarlos es
 * una petición de Inertia aparte.
 */

export const flightSuggestionsUrl = (startDate: string, endDate: string) =>
    `/flights/suggestions?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;

export const standaloneFlightSearchUrl = (query: string, date: string) => {
    const params = new URLSearchParams();

    if (query.trim()) {
        params.set('q', query.trim());
    }

    if (date) {
        params.set('date', date);
    }

    return `/flights/standalone?${params.toString()}`;
};

export const loadStandaloneFlights = async (url: string): Promise<FlightRecord[]> => {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    return data.flights ?? [];
};

// "MAD → LIS · IB3456"
export const flightRouteLabel = (flight: FlightRecord) =>
    [
        `${flight.origin.code ?? flight.origin.city ?? '—'} → ${flight.destination.code ?? flight.destination.city ?? '—'}`,
        flight.flight_number,
    ].filter(Boolean).join(' · ');

/*
 * Errores del servidor por vuelo (flight_ids.N, el índice en la lista
 * enviada): el vuelo ya no está disponible, normalmente porque otra pestaña
 * o petición lo ha añadido a otro viaje. Devuelve sus ids y las claves de
 * error para que el formulario las retire.
 */
export const unavailableFlightIds = (errors: Record<string, string>, sent: number[]): { ids: number[]; keys: string[] } => {
    const keys = Object.keys(errors).filter((key) => /^flight_ids\.\d+$/.test(key));
    const ids = keys
        .map((key) => sent[Number(key.split('.')[1])])
        .filter((id): id is number => typeof id === 'number');

    return { ids: [...new Set(ids)], keys };
};

export const unavailableFlightsMessage = (count: number) =>
    count === 1
        ? 'This flight has already been added to another trip and was removed from the list.'
        : `${count} flights have already been added to another trip and were removed from the list.`;

export const lostByDatesMessage = (count: number) =>
    count === 0
        ? ''
        : count === 1
            ? '1 accepted flight is outside the new dates and was removed. It comes back if the dates include it again.'
            : `${count} accepted flights are outside the new dates and were removed. They come back if the dates include them again.`;
