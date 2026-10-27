<script setup lang="ts">

import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { tripPhaseBadge, tripPhaseLabel } from '../../utils/tripPhase';
import TripVisitedMap from '../../components/trips/TripVisitedMap.vue';
import TripFlightMap from '../../components/trips/TripFlightMap.vue';

import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { tripDays as calendarTripDays, tripNights as calendarTripNights } from '../../utils/tripDates';
import { flightDepartureLabel, flightTimeWithZone } from '../../utils/flightTime';
import FlightForm from '../../components/flights/FlightForm.vue';
import type { FlightRecord as Flight } from '../../types/flights';
import { countCountries, type CountryStatus } from '../../utils/countryStatus';

import type { TripPhase } from '../../utils/tripPhase';
import { tripPath } from '../../utils/routes';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    status: 'planned' | 'completed' | 'cancelled';
    phase: TripPhase;
    can_complete: boolean;
}

interface City {
    id: number;
    name: string;
    country: string;
    iso_code: string;
    country_status?: CountryStatus;
    latitude: number | null;
    longitude: number | null;
}

interface Visit {
    id: number;
    city: City;
    visited_from: string | null;
    visited_until: string | null;
    notes: string | null;
}

interface Photo {
    id: number;
    // URLs autenticadas (PhotoFileController); nunca rutas de disco.
    url: string;
    thumbnail_url: string;
    original_filename: string;
    width: number | null;
    height: number | null;
    taken_at: string | null;
}

interface CitySearchResult {
    source: 'local' | 'geoapify';
    id: number | null;
    name: string;
    country: string;
    iso_code: string;
    region_code: string | null;
    latitude: number | null;
    longitude: number | null;
    external_id?: string | null;
}

interface DestinationGroup {
    country: string;
    iso_code: string;
    cities: string[];
}

interface GoogleMapList {
    id: number;
    name: string;
    url: string;
}

const props = defineProps<{
    trip: Trip;
    visits: Visit[];
    flights: Flight[];
    photos: Photo[];
    processing_photos_count: number;
    google_map_lists: GoogleMapList[];
    available_google_map_lists: GoogleMapList[];
}>();

/*
|--------------------------------------------------------------------------
| City search
|--------------------------------------------------------------------------
*/

const citySearch = ref('');
const cityResults = ref<CitySearchResult[]>([]);
const cityError = ref('');
const citySearchDone = ref(false);
const cityExternalSearched = ref(true);
const cityExternalUnavailable = ref(false);
const isSearchingWorldwide = ref(false);

/*
| Etiqueta secundaria de un resultado. La región distingue homónimos del mismo
| país (Portland OR / ME); si falta, se añaden coordenadas para no confundirlos.
*/
const cityResultLabel = (city: CitySearchResult) => {
    const parts = [city.region_code, city.country, city.iso_code].filter(Boolean);

    const twins = cityResults.value.filter(
        (other) =>
            other.name.toLowerCase() === city.name.toLowerCase() &&
            other.iso_code === city.iso_code &&
            (other.region_code ?? '') === (city.region_code ?? ''),
    );

    if (twins.length > 1 && city.latitude !== null && city.longitude !== null) {
        parts.push(`${Number(city.latitude).toFixed(2)}, ${Number(city.longitude).toFixed(2)}`);
    }

    return parts.join(' · ');
};

const runCitySearch = async (query: string, external: boolean) => {
    const requestId = ++citySearchRequestId;

    try {
        const response = await fetch(
            `/cities/search?q=${encodeURIComponent(query)}${external ? '&external=1' : ''}`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (requestId !== citySearchRequestId) {
            return;
        }

        cityResults.value = data.results ?? [];
        cityExternalSearched.value = data.external_searched ?? true;
        cityExternalUnavailable.value = data.external_unavailable ?? false;
        citySearchDone.value = true;
    } catch {
        if (requestId !== citySearchRequestId) {
            return;
        }

        cityResults.value = [];
        cityError.value = 'City search failed. Please try again.';
    }
};

const searchCitiesWorldwide = async () => {
    const query = citySearch.value.trim();

    if (query.length < 2) {
        return;
    }

    isSearchingWorldwide.value = true;

    try {
        await runCitySearch(query, true);
    } finally {
        isSearchingWorldwide.value = false;
    }
};

let citySearchTimeout: ReturnType<typeof setTimeout> | null = null;
let citySearchRequestId = 0;

const visitForm = useForm({
    city_id: 0,
    visited_from: props.trip.start_date ?? '',
    visited_until: props.trip.start_date ?? '',
    notes: '',
});

const searchCities = () => {
    cityError.value = '';
    citySearchDone.value = false;

    if (citySearchTimeout) {
        clearTimeout(citySearchTimeout);
    }

    const query = citySearch.value.trim();

    if (query.length < 2) {
        cityResults.value = [];
        citySearchRequestId++;
        return;
    }

    citySearchTimeout = setTimeout(() => runCitySearch(query, false), 300);
};

const addCityToTrip = (city: CitySearchResult) => {
    if (city.source === 'local' && city.id !== null) {
        visitForm.city_id = city.id;

        visitForm.post(`/trips/${props.trip.id}/visits`, {
            preserveScroll: true,

            onSuccess: () => {
                citySearch.value = '';
                cityResults.value = [];
                visitForm.reset();
            },
        });

        return;
    }

    if (city.source !== 'geoapify') {
        return;
    }

    cityError.value = '';

    fetch('/cities', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN':
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
        },
        // El servidor verifica la ciudad en Geoapify y obtiene sus datos.
        body: JSON.stringify({
            external_provider: 'geoapify',
            external_id: city.external_id,
            search_query: citySearch.value.trim(),
        }),
    })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error(
                    response.status === 503
                        ? 'City search is unavailable right now. Please try again later.'
                        : 'This city could not be verified. Please search again.',
                );
            }

            return response.json();
        })
        .then((data) => {
            visitForm.city_id = data.city.id;

            visitForm.post(`/trips/${props.trip.id}/visits`, {
                preserveScroll: true,

                onSuccess: () => {
                    citySearch.value = '';
                    cityResults.value = [];
                    visitForm.reset();
                },
            });
        })
        .catch((error) => {
            cityError.value =
                error instanceof Error
                    ? error.message
                    : 'This city could not be added.';
        });
};

/*
|--------------------------------------------------------------------------
| Visit editing
|--------------------------------------------------------------------------
*/

const editingVisitId = ref<number | null>(null);

const editVisitForm = useForm({
    city_id: 0,
    visited_from: '',
    visited_until: '',
    notes: '',
});

const startEditingVisit = (visit: Visit) => {
    editingVisitId.value = visit.id;

    editVisitForm.city_id = visit.city.id;
    editVisitForm.visited_from = visit.visited_from ?? '';
    editVisitForm.visited_until = visit.visited_until ?? '';
    editVisitForm.notes = visit.notes ?? '';
};

const cancelEditingVisit = () => {
    editingVisitId.value = null;
    editVisitForm.reset();
};

const saveVisit = (visit: Visit) => {
    editVisitForm.put(`/trips/${props.trip.id}/visits/${visit.id}`, {
        preserveScroll: true,

        onSuccess: () => {
            editingVisitId.value = null;
            editVisitForm.reset();

            visitForm.visited_from = props.trip.start_date ?? '';
            visitForm.visited_until = props.trip.start_date ?? '';
        },
    });
};

const deleteVisit = (visit: Visit) => {
    if (!confirm(`¿Quieres quitar ${visit.city.name} de este viaje?`)) {
        return;
    }

    router.delete(`/trips/${props.trip.id}/visits/${visit.id}`, {
        preserveScroll: true,
    });
};

/*
|--------------------------------------------------------------------------
| Flights
|--------------------------------------------------------------------------
*/

// Formulario de vuelo compartido (FlightForm): crear o editar.
const showFlightForm = ref(false);
const editingFlight = ref<Flight | null>(null);
const flightFormKey = ref(0);

const openFlightForm = (flight: Flight | null = null) => {
    editingFlight.value = flight;
    flightFormKey.value += 1;
    showFlightForm.value = true;
};

const closeFlightForm = () => {
    showFlightForm.value = false;
    editingFlight.value = null;
};

/*
|--------------------------------------------------------------------------
| Trip status
|--------------------------------------------------------------------------
| Un viaje solo se completa a mano (nunca por la fecha). Planificados en
| violeta, en curso y completados en cian, pendientes en ámbar y cancelados
| sin color (utils/tripPhase).
*/

const phaseLabel = computed(() => tripPhaseLabel[props.trip.phase]);
const phaseBadgeClass = computed(() => tripPhaseBadge[props.trip.phase]);

const isChangingStatus = ref(false);
const statusError = ref('');

const changeStatus = (status: Trip['status']) => {
    statusError.value = '';

    router.patch(`/trips/${props.trip.id}/status`, { status }, {
        preserveScroll: true,
        onStart: () => {
            isChangingStatus.value = true;
        },
        onError: (errors) => {
            statusError.value = errors.status ?? 'The trip status could not be changed.';
        },
        onFinish: () => {
            isChangingStatus.value = false;
        },
    });
};

const isDeletingTrip = ref(false);

const deleteTrip = () => {
    const confirmed = window.confirm(
        `Are you sure you want to delete "${props.trip.name}"? This action cannot be undone.`,
    );

    if (!confirmed) {
        return;
    }

    isDeletingTrip.value = true;

    router.delete(`/trips/${props.trip.id}`, {
        onFinish: () => {
            isDeletingTrip.value = false;
        },
    });
};

const deleteFlight = (flight: Flight) => {
    const confirmed = window.confirm(
        `Delete the flight ${flight.origin.code ?? flight.origin.city} → ${flight.destination.code ?? flight.destination.city}${flight.flight_number ? ` (${flight.flight_number})` : ''}? This action cannot be undone.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(`/trips/${props.trip.id}/flights/${flight.id}`, {
        preserveScroll: true,
    });
};

const flagEmoji = (isoCode: string) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(
                letter.charCodeAt(0) + 127397,
            ),
        )
        .join('');
};

/*
|--------------------------------------------------------------------------
| PHOTOS
|--------------------------------------------------------------------------
*/

const uploadsEnabled = usePage().props.features.uploads;
const photoInput = ref<HTMLInputElement | null>(null);

const isUploadingPhotos = ref(false);
const photoUploadMessage = ref('');

/*
| Las fotos se procesan en segundo plano: mientras haya alguna pendiente se
| recarga la lista cada 5 s (solo las props de fotos) y se detiene sola.
*/
let processingPoll: ReturnType<typeof setInterval> | null = null;

const stopProcessingPoll = () => {
    if (processingPoll) {
        clearInterval(processingPoll);
        processingPoll = null;
    }
};

watch(
    () => props.processing_photos_count,
    (count) => {
        if (count > 0 && !processingPoll) {
            processingPoll = setInterval(() => {
                router.reload({ only: ['photos', 'processing_photos_count'] });
            }, 5000);
        } else if (count === 0) {
            stopProcessingPoll();
        }
    },
    { immediate: true },
);

onBeforeUnmount(stopProcessingPoll);
const photoUploadSuccess = ref(false);

const openPhotoPicker = () => {
    if (isUploadingPhotos.value) {
        return;
    }

    photoInput.value?.click();
};

const selectedPhotos = ref<File[]>([]);

const handlePhotoSelection = async (event: Event) => {
    const input = event.target as HTMLInputElement;

    if (!input.files || !input.files.length) {
        return;
    }

    selectedPhotos.value = Array.from(input.files);

    const totalPhotos = selectedPhotos.value.length;

    isUploadingPhotos.value = true;
    photoUploadSuccess.value = false;
    photoUploadMessage.value =
        totalPhotos === 1
            ? 'Uploading 1 photo...'
            : `Uploading ${totalPhotos} photos...`;

    let uploadedCount = 0;

    try {
        for (const photo of selectedPhotos.value) {
            try {
                await uploadPhoto(photo);
                uploadedCount++;

                photoUploadMessage.value =
                    totalPhotos === 1
                        ? 'Uploading photo...'
                        : `Uploading ${uploadedCount} of ${totalPhotos} photos...`;
            } catch (error) {
                console.error(
                    `Error subiendo ${photo.name}:`,
                    error,
                );
            }
        }

        if (uploadedCount > 0) {
            photoUploadSuccess.value = uploadedCount === totalPhotos;

            photoUploadMessage.value =
                uploadedCount === totalPhotos
                    ? uploadedCount === 1
                        ? '1 photo uploaded successfully'
                        : `${uploadedCount} photos uploaded successfully`
                    : `${uploadedCount} of ${totalPhotos} photos uploaded`;

            router.reload({
                only: ['photos', 'processing_photos_count'],
            });

            setTimeout(() => {
                photoUploadMessage.value = '';
                photoUploadSuccess.value = false;
            }, 3000);
        }
    } finally {
        isUploadingPhotos.value = false;
        input.value = '';
        selectedPhotos.value = [];
    }
};

const uploadPhoto = async (file: File) => {
    const formData = new FormData();

    formData.append('photo', file);
    formData.append('trip_id', String(props.trip.id));

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    const response = await fetch('/photos', {
        method: 'POST',
        body: formData,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    });

    if (!response.ok) {
        let errorData = null;

        try {
            errorData = await response.json();
        } catch {
            // Ignore non-JSON responses.
        }

        console.error('Respuesta de Laravel:', errorData);

        throw new Error(`Error al subir ${file.name}`);
    }

    return response.json();
};

/*
|--------------------------------------------------------------------------
| GOOGLE MAPS
|--------------------------------------------------------------------------
*/

const showGoogleMapForm = ref(false);
const googleMapMode = ref<'create' | 'existing'>('create');
const selectedGoogleMapListId = ref<number | null>(null);

const googleMapForm = useForm({
    name: '',
    url: '',
});

const availableGoogleMapLists = computed(() =>
    props.available_google_map_lists.filter(
        (list) =>
            !props.google_map_lists.some(
                (attachedList) => attachedList.id === list.id,
            ),
    ),
);

const submitGoogleMap = () => {
    googleMapForm.post(`/trips/${props.trip.id}/google-maps`, {
        preserveScroll: true,

        onSuccess: () => {
            showGoogleMapForm.value = false;
            googleMapForm.reset();
        },
    });
};

const attachExistingGoogleMap = () => {
    if (!selectedGoogleMapListId.value) {
        return;
    }

    router.post(
        `/trips/${props.trip.id}/google-maps/${selectedGoogleMapListId.value}`,
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                showGoogleMapForm.value = false;
                selectedGoogleMapListId.value = null;
            },
        },
    );
};

const cancelGoogleMapForm = () => {
    showGoogleMapForm.value = false;
    googleMapForm.reset();
    selectedGoogleMapListId.value = null;
    googleMapMode.value = 'create';
};

/*
|--------------------------------------------------------------------------
| Derived data
|--------------------------------------------------------------------------
*/

const timeline = computed<Visit[]>(() => {
    return [...props.visits].sort((a, b) => {
        const dateA = a.visited_from
            ? new Date(a.visited_from).getTime()
            : 0;

        const dateB = b.visited_from
            ? new Date(b.visited_from).getTime()
            : 0;

        return dateA - dateB;
    });
});

const destinationsByCountry = computed<DestinationGroup[]>(() => {
    const groups = new Map<string, DestinationGroup>();

    props.visits.forEach((visit) => {
        const key = visit.city.iso_code;

        if (!groups.has(key)) {
            groups.set(key, {
                country: visit.city.country,
                iso_code: visit.city.iso_code,
                cities: [],
            });
        }

        const group = groups.get(key)!;

        if (!group.cities.includes(visit.city.name)) {
            group.cities.push(visit.city.name);
        }
    });

    return Array.from(groups.values()).sort((a, b) =>
        a.country.localeCompare(b.country),
    );
});

const cityCount = computed(() => {
    return new Set(
        props.visits.map((visit) => visit.city.id),
    ).size;
});

// Estados soberanos del viaje; los territorios se cuentan aparte.
const countryTotals = computed(() =>
    countCountries(
        props.visits
            .filter((visit) => visit.city.country_status)
            .map((visit) => ({ iso_code: visit.city.iso_code, country_status: visit.city.country_status! })),
    ),
);

const flightCount = computed(() => props.flights.length);

// Días de viaje (inicio y fin incluidos) y noches (días − 1).
const tripDays = computed(() => calendarTripDays(props.trip.start_date, props.trip.end_date));
const tripNights = computed(() => calendarTripNights(props.trip.start_date, props.trip.end_date));

/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/

const formatDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
};

const formatDateShort = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        timeZone: 'UTC',
    }).format(new Date(date));
};

const flightStatusLabel: Record<string, string> = { upcoming: 'Upcoming', pending: 'Unconfirmed', cancelled: 'Cancelled', flown: 'Flown' };
const flightStatusBadge: Record<string, string> = { upcoming: 'vyamap-badge-violet', pending: 'vyamap-badge-amber', cancelled: '', flown: 'vyamap-badge-cyan' };

const showPhotosPanel = computed(() => uploadsEnabled || props.photos.length > 0 || props.processing_photos_count > 0);
</script>

<template>
    <AppLayout :title="trip.name">
        <div class="vyamap-container vyamap-stack">
            <!-- CABECERA -->
            <section>
                <Link href="/trips" class="vyamap-button-ghost vyamap-button-sm -ml-3 mb-3">
                    <Icon name="arrow-left" :size="16" />
                    Trips
                </Link>

                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <p class="mb-2 flex items-center gap-2 text-sm text-[var(--vyamap-text-muted)]">
                            <Icon name="calendar" :size="16" />
                            <span class="vyamap-number">{{ formatDate(trip.start_date) }} – {{ formatDate(trip.end_date) }}</span>
                        </p>

                        <h1 class="vyamap-page-title">{{ trip.name }}</h1>

                        <!-- Estado del viaje: solo los completados cuentan como visitados. -->
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="vyamap-badge" :class="phaseBadgeClass">{{ phaseLabel }}</span>

                            <button
                                v-if="trip.can_complete"
                                type="button"
                                :disabled="isChangingStatus"
                                class="vyamap-button vyamap-button-sm"
                                @click="changeStatus('completed')"
                            >
                                <Icon name="check" :size="16" />
                                Mark as completed
                            </button>

                            <button
                                v-if="trip.status === 'planned'"
                                type="button"
                                :disabled="isChangingStatus"
                                class="vyamap-button-secondary vyamap-button-sm"
                                @click="changeStatus('cancelled')"
                            >
                                Cancel trip
                            </button>

                            <button
                                v-if="trip.status === 'cancelled'"
                                type="button"
                                :disabled="isChangingStatus"
                                class="vyamap-button-secondary vyamap-button-sm"
                                @click="changeStatus('planned')"
                            >
                                Restore trip
                            </button>

                            <button
                                v-if="trip.status === 'completed'"
                                type="button"
                                :disabled="isChangingStatus"
                                class="vyamap-button-ghost vyamap-button-sm"
                                @click="changeStatus('planned')"
                            >
                                Mark as planned
                            </button>
                        </div>

                        <p v-if="trip.phase === 'pending'" class="mt-3 max-w-xl text-sm text-[var(--vyamap-warning)]">
                            This trip has ended. Mark it as completed to count its places as visited.
                        </p>

                        <p v-if="statusError" role="alert" class="vyamap-error">{{ statusError }}</p>

                        <p v-if="trip.description" class="vyamap-page-description">{{ trip.description }}</p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link :href="tripPath(trip, 'edit')" class="vyamap-button-secondary">
                            <Icon name="edit" :size="16" />
                            Edit trip
                        </Link>
                        <button
                            type="button"
                            :disabled="isDeletingTrip"
                            class="vyamap-button-danger"
                            @click="deleteTrip"
                        >
                            <Icon name="trash" :size="16" />
                            {{ isDeletingTrip ? 'Deleting...' : 'Delete trip' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- CIFRAS Y DESTINOS -->
            <section class="vyamap-card overflow-hidden" aria-label="Trip summary">
                <dl class="grid grid-cols-2 sm:grid-cols-4">
                    <div class="vyamap-stat p-4 sm:p-5">
                        <dt class="vyamap-stat-label order-2">{{ tripDays === 1 ? 'Day' : 'Days' }}</dt>
                        <dd class="vyamap-stat-value order-1">{{ tripDays ?? '—' }}</dd>
                        <dd v-if="tripNights" class="vyamap-stat-hint order-3">{{ tripNights }} {{ tripNights === 1 ? 'night' : 'nights' }}</dd>
                    </div>
                    <div class="vyamap-stat border-l border-[var(--vyamap-border)] p-4 sm:p-5">
                        <dt class="vyamap-stat-label order-2">Cities</dt>
                        <dd class="vyamap-stat-value order-1">{{ cityCount }}</dd>
                    </div>
                    <div class="vyamap-stat border-t border-[var(--vyamap-border)] p-4 sm:border-l sm:border-t-0 sm:p-5">
                        <dt class="vyamap-stat-label order-2">{{ countryTotals.countries === 1 ? 'Country' : 'Countries' }}</dt>
                        <dd class="vyamap-stat-value order-1">{{ countryTotals.countries }}</dd>
                        <dd v-if="countryTotals.territories" class="vyamap-stat-hint order-3">
                            + {{ countryTotals.territories }} {{ countryTotals.territories === 1 ? 'territory' : 'territories' }}
                        </dd>
                    </div>
                    <div class="vyamap-stat border-l border-t border-[var(--vyamap-border)] p-4 sm:border-t-0 sm:p-5">
                        <dt class="vyamap-stat-label order-2">Flights</dt>
                        <dd class="vyamap-stat-value order-1">{{ flightCount }}</dd>
                    </div>
                </dl>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-[var(--vyamap-border)] bg-[var(--vyamap-surface-muted)] px-5 py-3">
                    <span class="text-sm font-semibold">Destinations</span>
                    <template v-if="destinationsByCountry.length">
                        <span
                            v-for="group in destinationsByCountry"
                            :key="group.iso_code"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span aria-hidden="true" class="text-base leading-none">{{ flagEmoji(group.iso_code) }}</span>
                            <span class="font-medium">{{ group.country }}</span>
                            <span class="text-[var(--vyamap-text-muted)]">{{ group.cities.join(', ') }}</span>
                        </span>
                    </template>
                    <span v-else class="text-sm text-[var(--vyamap-text-muted)]">No destinations yet. Add a city to the itinerary.</span>
                </div>
            </section>

            <!-- MAPA Y FOTOS -->
            <section class="grid grid-cols-1 gap-4" :class="showPhotosPanel ? 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]' : ''">
                <div class="vyamap-map-card h-[340px] sm:h-[460px]">
                    <TripVisitedMap :phase="trip.phase" :visits="visits" />
                </div>

                <div v-if="showPhotosPanel" class="vyamap-panel flex flex-col">
                    <div class="vyamap-panel-header">
                        <h2 class="vyamap-h2">Photos</h2>
                        <div class="flex items-center gap-2">
                            <Link v-if="photos.length" :href="tripPath(trip, 'photos')" class="vyamap-link text-sm">View all</Link>
                            <button
                                v-if="uploadsEnabled"
                                type="button"
                                class="vyamap-icon-button"
                                :disabled="isUploadingPhotos"
                                aria-label="Add photos"
                                title="Add photos"
                                @click="openPhotoPicker"
                            >
                                <span v-if="isUploadingPhotos" class="h-4 w-4 animate-spin rounded-full border-2 border-[var(--vyamap-border-strong)] border-t-[var(--vyamap-cyan)]" />
                                <Icon v-else name="image-plus" :size="18" />
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="photoUploadMessage"
                        role="status"
                        class="mb-3 flex items-center gap-3 rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)] px-3 py-2.5 text-sm"
                    >
                        <span v-if="isUploadingPhotos" class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-[var(--vyamap-border-strong)] border-t-[var(--vyamap-cyan)]" />
                        <Icon v-else-if="photoUploadSuccess" name="check" :size="16" class="text-[var(--vyamap-success)]" />
                        {{ photoUploadMessage }}
                    </p>

                    <p
                        v-if="processing_photos_count > 0"
                        role="status"
                        class="mb-3 flex items-center gap-3 rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)] px-3 py-2.5 text-sm text-[var(--vyamap-text-muted)]"
                    >
                        <span class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-[var(--vyamap-border-strong)] border-t-[var(--vyamap-cyan)]" />
                        {{ processing_photos_count === 1 ? '1 photo is being processed' : `${processing_photos_count} photos are being processed` }}
                        and will appear here when ready.
                    </p>

                    <!-- Mismos formatos que valida el servidor (HEIC no es compatible). -->
                    <input
                        v-if="uploadsEnabled"
                        ref="photoInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="hidden"
                        @change="handlePhotoSelection"
                    />

                    <ul v-if="photos.length" class="grid grid-cols-3 gap-2">
                        <li v-for="photo in photos.slice(0, 6)" :key="photo.id">
                            <Link
                                :href="tripPath(trip, 'photos')"
                                class="block aspect-square overflow-hidden rounded-[var(--vyamap-radius-sm)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)]"
                            >
                                <img :src="photo.thumbnail_url" :alt="photo.original_filename" class="h-full w-full object-cover" loading="lazy" />
                            </Link>
                        </li>
                    </ul>

                    <EmptyState
                        v-else-if="!processing_photos_count"
                        compact
                        icon="photo"
                        title="No photos yet"
                        text="Photos are private. Their date and location are read from the file."
                        class="flex-1"
                    >
                        <template v-if="uploadsEnabled" #actions>
                            <button type="button" class="vyamap-button-secondary vyamap-button-sm" :disabled="isUploadingPhotos" @click="openPhotoPicker">
                                <Icon name="image-plus" :size="16" />
                                Add photos
                            </button>
                        </template>
                    </EmptyState>
                </div>
            </section>

            <!-- ITINERARIO Y VUELOS -->
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
                <!-- ITINERARIO -->
                <div class="vyamap-panel z-20">
                    <div class="vyamap-panel-header !mb-3">
                        <h2 class="vyamap-h2">Itinerary</h2>
                        <span class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">{{ timeline.length }} {{ timeline.length === 1 ? 'city' : 'cities' }}</span>
                    </div>

                    <!-- Buscador de ciudades -->
                    <div class="relative mb-4">
                        <label for="city-search" class="sr-only">Add a city</label>
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--vyamap-text-subtle)]">
                            <Icon name="search" :size="18" />
                        </span>
                        <input
                            id="city-search"
                            v-model="citySearch"
                            type="search"
                            autocomplete="off"
                            placeholder="Add a city to the itinerary…"
                            class="vyamap-input !pl-10"
                            aria-controls="city-search-results"
                            @input="searchCities"
                        />

                        <p v-if="cityError" role="alert" class="vyamap-error">{{ cityError }}</p>
                        <p
                            v-for="(message, field) in visitForm.errors"
                            :key="field"
                            role="alert"
                            class="vyamap-error"
                        >
                            {{ message }}
                        </p>

                        <div
                            v-if="citySearchDone && citySearch.trim().length >= 2"
                            id="city-search-results"
                            class="vyamap-popover absolute inset-x-0 top-full z-40 mt-2 max-h-80 overflow-y-auto rounded-[var(--vyamap-radius-md)] p-1"
                        >
                            <p v-if="!cityResults.length" class="px-3 py-2.5 text-sm text-[var(--vyamap-text-muted)]">
                                {{ cityExternalUnavailable ? 'No cities found here and worldwide search is unavailable right now.' : 'No cities found.' }}
                            </p>

                            <button
                                v-for="city in cityResults"
                                :key="`${city.source}-${city.id ?? city.external_id ?? city.name}`"
                                type="button"
                                class="vyamap-row flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-[var(--vyamap-surface-raised)]"
                                @click="addCityToTrip(city)"
                            >
                                <Icon name="pin" :size="18" class="text-[var(--vyamap-cyan-soft)]" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ city.name }}</span>
                                    <span class="block truncate text-[0.8125rem] text-[var(--vyamap-text-muted)]">{{ cityResultLabel(city) }}</span>
                                </span>
                            </button>

                            <button
                                v-if="!cityExternalSearched"
                                type="button"
                                :disabled="isSearchingWorldwide"
                                class="vyamap-row mt-1 flex w-full items-center gap-2 border-t border-[var(--vyamap-border)] px-3 py-2.5 text-left text-sm text-[var(--vyamap-cyan-soft)] hover:bg-[var(--vyamap-surface-raised)]"
                                @click="searchCitiesWorldwide"
                            >
                                <Icon name="globe" :size="16" />
                                {{ isSearchingWorldwide ? 'Searching worldwide…' : 'Not the one? Search worldwide' }}
                            </button>

                            <p
                                v-else-if="cityExternalUnavailable && cityResults.length"
                                class="mt-1 border-t border-[var(--vyamap-border)] px-3 py-2.5 text-sm text-[var(--vyamap-text-muted)]"
                            >
                                Worldwide search is unavailable right now.
                            </p>
                        </div>
                    </div>

                    <ol v-if="timeline.length" class="flex flex-col">
                        <li
                            v-for="(visit, index) in timeline"
                            :key="visit.id"
                            class="relative pb-5 pl-12 last:pb-0"
                        >
                            <span class="vyamap-timeline-node">{{ index + 1 }}</span>
                            <span v-if="index < timeline.length - 1" class="vyamap-timeline-line" aria-hidden="true" />

                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 pt-0.5">
                                    <h3 class="flex items-center gap-2 text-base font-semibold">
                                        {{ visit.city.name }}
                                        <span aria-hidden="true" class="text-[0.9375rem] leading-none">{{ flagEmoji(visit.city.iso_code) }}</span>
                                    </h3>
                                    <p class="text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                        {{ visit.city.country }} ·
                                        <span class="vyamap-number">{{ formatDateShort(visit.visited_from) }} – {{ formatDateShort(visit.visited_until) }}</span>
                                    </p>
                                </div>

                                <div v-if="editingVisitId !== visit.id" class="flex shrink-0 gap-1">
                                    <button
                                        type="button"
                                        class="vyamap-button-ghost vyamap-button-sm !px-2"
                                        :aria-label="`Edit ${visit.city.name}`"
                                        title="Edit"
                                        @click="startEditingVisit(visit)"
                                    >
                                        <Icon name="edit" :size="16" />
                                    </button>
                                    <button
                                        type="button"
                                        class="vyamap-button-ghost vyamap-button-sm !px-2 hover:!text-[var(--vyamap-danger)]"
                                        :aria-label="`Remove ${visit.city.name}`"
                                        title="Remove"
                                        @click="deleteVisit(visit)"
                                    >
                                        <Icon name="trash" :size="16" />
                                    </button>
                                </div>
                            </div>

                            <p v-if="editingVisitId !== visit.id && visit.notes" class="mt-2 text-sm text-[var(--vyamap-text-muted)]">
                                {{ visit.notes }}
                            </p>

                            <form
                                v-if="editingVisitId === visit.id"
                                class="mt-3 rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-muted)] p-4"
                                @submit.prevent="saveVisit(visit)"
                            >
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label :for="`visit-from-${visit.id}`" class="vyamap-label">From</label>
                                        <input
                                            :id="`visit-from-${visit.id}`"
                                            v-model="editVisitForm.visited_from"
                                            type="date"
                                            :min="trip.start_date ?? undefined"
                                            :max="trip.end_date ?? undefined"
                                            class="vyamap-input"
                                        />
                                    </div>
                                    <div>
                                        <label :for="`visit-until-${visit.id}`" class="vyamap-label">Until</label>
                                        <input
                                            :id="`visit-until-${visit.id}`"
                                            v-model="editVisitForm.visited_until"
                                            type="date"
                                            :min="editVisitForm.visited_from || trip.start_date || undefined"
                                            :max="trip.end_date ?? undefined"
                                            class="vyamap-input"
                                        />
                                    </div>
                                </div>
                                <p v-if="editVisitForm.errors.visited_from" class="vyamap-error">{{ editVisitForm.errors.visited_from }}</p>
                                <p v-if="editVisitForm.errors.visited_until" class="vyamap-error">{{ editVisitForm.errors.visited_until }}</p>

                                <label :for="`visit-notes-${visit.id}`" class="vyamap-label mt-3">Notes</label>
                                <textarea
                                    :id="`visit-notes-${visit.id}`"
                                    v-model="editVisitForm.notes"
                                    rows="3"
                                    placeholder="What did you see?"
                                    class="vyamap-textarea"
                                />
                                <p v-if="editVisitForm.errors.notes" class="vyamap-error">{{ editVisitForm.errors.notes }}</p>

                                <div class="mt-3 flex gap-2">
                                    <button type="submit" :disabled="editVisitForm.processing" class="vyamap-button vyamap-button-sm">Save</button>
                                    <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="cancelEditingVisit">Cancel</button>
                                </div>
                            </form>
                        </li>
                    </ol>

                    <EmptyState
                        v-else
                        compact
                        icon="pin"
                        title="No cities yet"
                        text="Search above for each city you visited. Completed trips count their cities and countries as visited."
                    />
                </div>

                <!-- VUELOS -->
                <div class="vyamap-panel">
                    <div class="vyamap-panel-header">
                        <h2 class="vyamap-h2">Flights</h2>
                        <button
                            v-if="!showFlightForm"
                            type="button"
                            class="vyamap-button-secondary vyamap-button-sm"
                            @click="openFlightForm()"
                        >
                            <Icon name="plus" :size="16" />
                            Add flight
                        </button>
                    </div>

                    <div v-if="flights.length" class="vyamap-map-card mb-4 h-[240px] !rounded-[var(--vyamap-radius-lg)] sm:h-[280px]">
                        <TripFlightMap :flights="flights" />
                    </div>

                    <!-- FORMULARIO DE VUELO (componente compartido con Flight History) -->
                    <div
                        v-if="showFlightForm"
                        class="mb-4 rounded-[var(--vyamap-radius-lg)] border border-[rgba(34,211,238,0.3)] bg-[var(--vyamap-surface-muted)] p-4 sm:p-5"
                    >
                        <h3 class="vyamap-h3 mb-4">{{ editingFlight ? 'Edit flight' : 'Add flight' }}</h3>
                        <FlightForm
                            :key="flightFormKey"
                            :flight="editingFlight"
                            :trip-id="trip.id"
                            :default-date="trip.start_date"
                            @close="closeFlightForm"
                        />
                    </div>

                    <ul v-if="flights.length" class="flex flex-col gap-2.5">
                        <li v-for="flight in flights" :key="flight.id" class="vyamap-flight">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="flex items-center gap-2">
                                    <span class="vyamap-code text-sm">{{ flight.flight_number || 'Flight' }}</span>
                                    <span v-if="flight.airline" class="text-[0.8125rem] text-[var(--vyamap-text-muted)]">{{ flight.airline }}</span>
                                </span>
                                <span v-if="flight.status !== 'flown'" class="vyamap-badge" :class="flightStatusBadge[flight.status]">
                                    {{ flightStatusLabel[flight.status] }}
                                </span>
                            </div>

                            <div class="mt-2 flex items-center gap-3 text-sm">
                                <span class="truncate font-medium">{{ flight.origin.city }}</span>
                                <span class="vyamap-route-line" aria-hidden="true" />
                                <span class="truncate font-medium">{{ flight.destination.city }}</span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                                <dl class="flex gap-5 text-[0.8125rem]">
                                    <div>
                                        <dt class="text-[var(--vyamap-text-subtle)]">Departure</dt>
                                        <dd class="vyamap-number text-[var(--vyamap-text-muted)]">{{ flightDepartureLabel(flight, false) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[var(--vyamap-text-subtle)]">Arrival</dt>
                                        <dd class="vyamap-number text-[var(--vyamap-text-muted)]">{{ flight.arrival ? flightTimeWithZone(flight.arrival, flight.destination.timezone) : '—' }}</dd>
                                    </div>
                                </dl>
                                <div class="flex gap-1">
                                    <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="openFlightForm(flight)">
                                        <Icon name="edit" :size="16" />
                                        Edit
                                    </button>
                                    <button type="button" class="vyamap-button-ghost vyamap-button-sm hover:!text-[var(--vyamap-danger)]" @click="deleteFlight(flight)">
                                        <Icon name="trash" :size="16" />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <EmptyState
                        v-else-if="!showFlightForm"
                        compact
                        icon="flight"
                        title="No flights yet"
                        text="Add the flights of this trip to see their routes and distance."
                    >
                        <template #actions>
                            <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="openFlightForm()">
                                <Icon name="plus" :size="16" />
                                Add flight
                            </button>
                        </template>
                    </EmptyState>
                </div>
            </section>

            <!-- GOOGLE MAPS -->
            <section class="vyamap-panel">
                <div class="vyamap-panel-header">
                    <div>
                        <h2 class="vyamap-h2">Google Maps lists</h2>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Maps and lists used while planning the trip.</p>
                    </div>
                    <button
                        v-if="!showGoogleMapForm"
                        type="button"
                        class="vyamap-button-secondary vyamap-button-sm"
                        @click="showGoogleMapForm = true; googleMapMode = 'create'"
                    >
                        <Icon name="plus" :size="16" />
                        Add map
                    </button>
                </div>

                <div
                    v-if="showGoogleMapForm"
                    class="mb-4 rounded-[var(--vyamap-radius-lg)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-muted)] p-4 sm:p-5"
                >
                    <div class="vyamap-segmented" role="group" aria-label="How to add the list">
                        <button type="button" class="vyamap-segment" :aria-pressed="googleMapMode === 'create'" @click="googleMapMode = 'create'">
                            Create new list
                        </button>
                        <button type="button" class="vyamap-segment" :aria-pressed="googleMapMode === 'existing'" @click="googleMapMode = 'existing'">
                            Use existing list
                        </button>
                    </div>

                    <form v-if="googleMapMode === 'create'" class="mt-4" @submit.prevent="submitGoogleMap">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="google-map-name" class="vyamap-label">List name</label>
                                <input id="google-map-name" v-model="googleMapForm.name" type="text" placeholder="Italy" class="vyamap-input" />
                                <p v-if="googleMapForm.errors.name" class="vyamap-error">{{ googleMapForm.errors.name }}</p>
                            </div>
                            <div>
                                <label for="google-map-url" class="vyamap-label">Google Maps URL</label>
                                <input id="google-map-url" v-model="googleMapForm.url" type="url" placeholder="https://maps.app.goo.gl/..." class="vyamap-input" />
                                <p v-if="googleMapForm.errors.url" class="vyamap-error">{{ googleMapForm.errors.url }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button type="submit" class="vyamap-button vyamap-button-sm" :disabled="googleMapForm.processing">Add list</button>
                            <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="cancelGoogleMapForm">Cancel</button>
                        </div>
                    </form>

                    <div v-else class="mt-4">
                        <div v-if="availableGoogleMapLists.length">
                            <label for="google-map-existing" class="vyamap-label">Select a Google Maps list</label>
                            <select id="google-map-existing" v-model="selectedGoogleMapListId" class="vyamap-input">
                                <option :value="null">Select a list...</option>
                                <option v-for="mapList in availableGoogleMapLists" :key="mapList.id" :value="mapList.id">
                                    {{ mapList.name }}
                                </option>
                            </select>
                            <div class="mt-4 flex gap-2">
                                <button type="button" class="vyamap-button vyamap-button-sm" :disabled="!selectedGoogleMapListId" @click="attachExistingGoogleMap">Add list</button>
                                <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="cancelGoogleMapForm">Cancel</button>
                            </div>
                        </div>

                        <div v-else class="text-sm text-[var(--vyamap-text-muted)]">
                            You don't have any other Google Maps lists available.
                            <button type="button" class="vyamap-link ml-1" @click="googleMapMode = 'create'">Create a new list</button>
                        </div>
                    </div>
                </div>

                <ul v-if="props.google_map_lists.length" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <li
                        v-for="mapList in props.google_map_lists"
                        :key="mapList.id"
                        class="vyamap-flight flex items-center gap-3"
                    >
                        <span class="vyamap-empty-icon !h-10 !w-10 shrink-0">
                            <Icon name="map" :size="18" />
                        </span>
                        <a :href="mapList.url" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1 group">
                            <span class="block truncate text-sm font-semibold group-hover:text-[var(--vyamap-cyan-soft)]">{{ mapList.name }}</span>
                            <span class="flex items-center gap-1 text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                Open in Google Maps
                                <Icon name="external" :size="14" />
                            </span>
                        </a>
                        <button
                            type="button"
                            class="vyamap-button-ghost vyamap-button-sm !px-2 hover:!text-[var(--vyamap-danger)]"
                            :aria-label="`Remove ${mapList.name}`"
                            title="Remove"
                            @click="router.delete(`/trips/${props.trip.id}/google-maps/${mapList.id}`, { preserveScroll: true })"
                        >
                            <Icon name="trash" :size="16" />
                        </button>
                    </li>
                </ul>

                <EmptyState
                    v-else-if="!showGoogleMapForm"
                    compact
                    icon="map"
                    title="No Google Maps lists linked to this trip yet"
                    text="Link the saved places you used to plan it, so they are one click away."
                />
            </section>
        </div>
    </AppLayout>
</template>
