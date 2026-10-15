/*
 * Estadísticas calculadas en el servidor (App\Services\TravelStatistics).
 * Las páginas solo eligen el periodo; nunca recalculan las cifras.
 */

export interface PlaceCounts {
    countries: number;
    territories: number;
    cities: number;
    continents: number;
    days: number;
    trips: number;
}

export interface NewCountry {
    iso_code: string;
    name: string;
}

export interface YearPlaceStats extends PlaceCounts {
    new_countries: NewCountry[];
    new_territories: number;
}

export interface ContinentProgress {
    code: string;
    name: string;
    countries: number;
    territories: number;
    total_countries: number;
    visited: boolean;
}

export interface PlaceStats {
    total: PlaceCounts & { percentage: number; total_countries: number };
    by_year: Record<string, YearPlaceStats>;
    continents: { items: ContinentProgress[]; without_continent: string[] };
    undated_visits: number;
    invalid_trips: number;
}

export interface TopAirport {
    id: number;
    code: string | null;
    airport: string;
    city: string | null;
    count: number;
}

// null: no hay ningún vuelo con el dato necesario (no es cero).
export interface FlightStats {
    flights: number;
    distance_km: number | null;
    flights_with_distance: number;
    average_distance_km: number | null;
    air_minutes: number | null;
    flights_with_times: number;
    flights_without_times: number;
    average_air_minutes: number | null;
    airports: number;
    cities: number;
    longest: {
        id: number;
        origin: string | null;
        destination: string | null;
        distance_km: number;
        departure_date: string | null;
    } | null;
    top_airports: TopAirport[];
}

// Cifras de un año sin datos de lugares: cero real (ese año no hay visitas).
export const emptyYear = (): YearPlaceStats => ({
    countries: 0,
    territories: 0,
    cities: 0,
    continents: 0,
    days: 0,
    trips: 0,
    new_countries: [],
    new_territories: 0,
});
