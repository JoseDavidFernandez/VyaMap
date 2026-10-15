/*
 * Fechas y horas de vuelo en la hora local del aeropuerto.
 *
 * El servidor guarda salidas y llegadas en UTC. La salida se muestra en la
 * zona horaria del aeropuerto de origen y la llegada en la del destino, nunca
 * en la del navegador. Sin zona conocida se usa UTC y se indica.
 */

const zoneOf = (timezone: string | null | undefined) => timezone || 'UTC';

const parse = (value: string | null | undefined): Date | null => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
};

const part = (date: Date, timezone: string | null | undefined, options: Intl.DateTimeFormatOptions, type: Intl.DateTimeFormatPartTypes) =>
    new Intl.DateTimeFormat('en-GB', { timeZone: zoneOf(timezone), ...options })
        .formatToParts(date)
        .find((item) => item.type === type)?.value ?? '';

// 08:00
export const flightTime = (value: string | null | undefined, timezone: string | null | undefined): string => {
    const date = parse(value);

    return date
        ? new Intl.DateTimeFormat('en-GB', { timeZone: zoneOf(timezone), hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(date)
        : '—';
};

// 10 Mar 2026 (o 10 Mar con withYear = false)
export const flightDate = (value: string | null | undefined, timezone: string | null | undefined, withYear = true): string => {
    const date = parse(value);

    return date
        ? new Intl.DateTimeFormat('en-GB', {
            timeZone: zoneOf(timezone),
            day: '2-digit',
            month: 'short',
            ...(withYear ? { year: 'numeric' as const } : {}),
        }).format(date)
        : '—';
};

// Abreviatura de la zona en ese momento: CET, CEST, GMT+9…
export const flightZone = (value: string | null | undefined, timezone: string | null | undefined): string => {
    const date = parse(value);

    return date ? part(date, timezone, { timeZoneName: 'short' }, 'timeZoneName') : '';
};

// 08:00 CET
export const flightTimeWithZone = (value: string | null | undefined, timezone: string | null | undefined): string => {
    const time = flightTime(value, timezone);
    const zone = flightZone(value, timezone);

    return zone && time !== '—' ? `${time} ${zone}` : time;
};

// Año de la fecha local (para filtrar vuelos por año).
export const flightYear = (value: string | null | undefined, timezone: string | null | undefined): number | null => {
    const date = parse(value);

    return date ? Number(part(date, timezone, { year: 'numeric' }, 'year')) : null;
};

// Fecha de calendario (YYYY-MM-DD) como "12 Apr 2019", sin pasar por la zona del navegador.
export const calendarDate = (date: string | null | undefined, withYear = true): string => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        timeZone: 'UTC',
        day: '2-digit',
        month: 'short',
        ...(withYear ? { year: 'numeric' as const } : {}),
    }).format(new Date(`${date.slice(0, 10)}T00:00:00Z`));
};

/*
 * Salida de un vuelo para mostrar: fecha local (siempre) y hora con zona solo
 * si se conoce. "12 Apr 2019" o "10 Mar 2026 · 10:00 CET".
 */
export const flightDepartureLabel = (
    flight: { departure_date: string; departure: string | null; origin: { timezone: string | null } },
    withYear = true,
): string => {
    const date = calendarDate(flight.departure_date, withYear);

    return flight.departure ? `${date} · ${flightTimeWithZone(flight.departure, flight.origin.timezone)}` : date;
};
