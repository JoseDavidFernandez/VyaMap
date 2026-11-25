import { computed, ref } from 'vue';
import { unavailableFlightIds } from '../utils/standaloneFlights';

/*
 * Vuelos sugeridos y aceptados en los formularios de crear y editar viaje
 * (TripFlightSuggestions). Si al guardar el servidor rechaza algún vuelo
 * porque ya no está disponible (flight_ids.N), se quita de la lista, se
 * recargan las sugerencias y se avisa; el resto del formulario no cambia y
 * se puede volver a guardar.
 */

interface FormWithFlights {
    flight_ids: number[];
    errors: Record<string, string | undefined>;
    clearErrors: (...fields: never[]) => unknown;
}

interface SuggestionsHandle {
    markUnavailable: (ids: number[]) => Promise<void>;
}

export const useAcceptedFlights = (form: FormWithFlights) => {
    const suggestions = ref<SuggestionsHandle | null>(null);
    let sent: number[] = [];

    // Llamar justo antes de enviar: los índices de flight_ids.N son los de esta lista.
    const rememberSent = () => {
        sent = [...form.flight_ids];
    };

    const onError = (errors: Record<string, string>) => {
        const { ids, keys } = unavailableFlightIds(errors, sent);

        if (ids.length) {
            (form.clearErrors as (...fields: string[]) => unknown)(...keys);
            void suggestions.value?.markUnavailable(ids);
        }
    };

    // Otro error de la lista de vuelos (p. ej. demasiados): se muestra tal cual.
    const flightIdsError = computed(() =>
        Object.entries(form.errors).find(([key, message]) => key.startsWith('flight_ids') && message)?.[1] ?? '',
    );

    return { suggestions, rememberSent, onError, flightIdsError };
};
