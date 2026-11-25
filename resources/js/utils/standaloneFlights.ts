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
