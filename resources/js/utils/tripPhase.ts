/**
 * Texto e insignia de la fase de un viaje, comunes a todas las páginas.
 * Cian: en curso o completado; violeta: planificado; ámbar: pendiente de
 * confirmar (la fecha de fin pasó y sigue planificado); gris: cancelado.
 */
export type TripPhase = 'upcoming' | 'ongoing' | 'pending' | 'completed' | 'cancelled';

export const tripPhaseLabel: Record<TripPhase, string> = {
    upcoming: 'Planned',
    ongoing: 'In progress',
    pending: 'Pending confirmation',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const tripPhaseBadge: Record<TripPhase, string> = {
    upcoming: 'vyamap-badge-violet',
    ongoing: 'vyamap-badge-cyan',
    pending: 'vyamap-badge-amber',
    completed: 'vyamap-badge-cyan',
    cancelled: '',
};
