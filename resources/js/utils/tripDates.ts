/*
 * Duración de un viaje a partir de sus fechas de calendario (YYYY-MM-DD).
 *
 * - Días: días naturales de inicio a fin, ambos incluidos (fin − inicio + 1).
 * - Noches: días − 1.
 *
 * Se calcula en UTC sobre la fecha, sin horas: un cambio de horario de verano
 * no resta ni suma un día.
 */

const toUtcDay = (date: string | null | undefined): number | null => {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(date ?? '');

    if (!match) {
        return null;
    }

    return Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])) / 86_400_000;
};

export const tripDays = (start: string | null | undefined, end: string | null | undefined): number | null => {
    const from = toUtcDay(start);
    const until = toUtcDay(end);

    if (from === null || until === null || until < from) {
        return null;
    }

    return until - from + 1;
};

export const tripNights = (start: string | null | undefined, end: string | null | undefined): number | null => {
    const days = tripDays(start, end);

    return days === null ? null : days - 1;
};

// Año de calendario de una fecha YYYY-MM-DD (o de un ISO), sin pasar por la zona horaria del navegador.
export const calendarYear = (date: string | null | undefined): number | null => {
    const match = /^(\d{4})-/.exec(date ?? '');

    return match ? Number(match[1]) : null;
};

/*
 * Un viaje o una visita pertenece a cada año natural que toca (misma regla que
 * App\Services\TravelStatistics): del 30-12 al 02-01 está en los dos años.
 * Solo se usa para filtrar listas; las cifras llegan calculadas del servidor.
 */
export const touchesYear = (start: string | null | undefined, end: string | null | undefined, year: number): boolean => {
    const from = calendarYear(start);
    const until = calendarYear(end ?? start);

    return from !== null && until !== null && from <= year && year <= until;
};

/*
 * Una fecha YYYY-MM-DD fuera de [inicio, fin], ambos incluidos. Se usa para
 * avisar de un vuelo fuera de las fechas de su viaje (no se bloquea: D3).
 * Sin fechas completas no hay aviso.
 */
export const isOutsideDates = (date: string | null | undefined, start: string | null | undefined, end: string | null | undefined): boolean => {
    const day = toUtcDay(date);
    const from = toUtcDay(start);
    const until = toUtcDay(end);

    if (day === null || from === null || until === null) {
        return false;
    }

    return day < from || day > until;
};
