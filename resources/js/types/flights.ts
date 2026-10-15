/*
 * Vuelo tal como lo envía el servidor (App\Support\FlightPresenter).
 * Sin horas, departure/arrival/duration_minutes son null: nunca se inventan.
 */

export type FlightStatus = 'flown' | 'upcoming' | 'pending' | 'cancelled';

export interface FlightAirport {
    id: number;
    code: string | null;
    airport: string;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    timezone: string | null;
}

export interface FlightRecord {
    id: number;
    status: FlightStatus;
    confirmed: boolean;
    departure_date: string;
    flight_number: string | null;
    airline: string | null;
    departure: string | null;
    arrival: string | null;
    distance_km: number | null;
    duration_minutes: number | null;
    trip: { id: number; name: string } | null;
    origin: FlightAirport;
    destination: FlightAirport;
}

export interface TripOption {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
}

// Resultado de /airports/search.
export interface AirportSearchResult {
    id: number;
    name: string;
    iata_code: string | null;
    icao_code: string | null;
    city: string | null;
    country: string | null;
    timezone: string | null;
}
