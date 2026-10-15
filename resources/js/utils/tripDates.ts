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
