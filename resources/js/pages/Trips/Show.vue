<script setup lang="ts">

import AppLayout from '../../layouts/AppLayout.vue';
import TripMap from '../../components/trips/TripMap.vue';

import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
}

interface City {
    id: number;
    name: string;
    country: string;
    iso_code: string;
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

interface FlightAirport {
    id: number;
    name: string;
    city: string;
    latitude: number;
    longitude: number;
}

interface Flight {
    id: number;
    flight_number: string;
    airline: string | null;
    departure: string | null;
    arrival: string | null;
    origin: FlightAirport;
    destination: FlightAirport;
}

interface Photo {
    id: number;
    path: string;
    thumbnail_path: string | null;
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

interface AirportSearchResult {
    id: number;
    name: string;
    iata_code: string | null;
    icao_code: string | null;
    city: string | null;
    country: string | null;
    latitude: number;
    longitude: number;
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

let citySearchTimeout: ReturnType<typeof setTimeout> | null = null;
let citySearchRequestId = 0;

const visitForm = useForm({
    city_id: 0,
    visited_from: props.trip.start_date ?? '',
    visited_until: props.trip.start_date ?? '',
    notes: '',
});

const searchCities = () => {
    if (citySearchTimeout) {
        clearTimeout(citySearchTimeout);
    }

    const query = citySearch.value.trim();

    if (query.length < 2) {
        cityResults.value = [];
        return;
    }

    const requestId = ++citySearchRequestId;

    citySearchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(
                `/cities/search?q=${encodeURIComponent(query)}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            const data = await response.json();

            if (requestId !== citySearchRequestId) {
                return;
            }

            cityResults.value = data.results ?? [];
        } catch (error) {
            if (requestId !== citySearchRequestId) {
                return;
            }

            console.error('Error searching cities:', error);
            cityResults.value = [];
        }
    }, 300);
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
        body: JSON.stringify({
            name: city.name,
            country: city.country,
            iso_code: city.iso_code,
            latitude: city.latitude,
            longitude: city.longitude,
            region_code: city.region_code,
            external_provider: 'geoapify',
            external_id: city.external_id,
        }),
    })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error('Unable to create city.');
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
            console.error('Error creating city:', error);
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

/*
|--------------------------------------------------------------------------
| Flights
|--------------------------------------------------------------------------
*/

const showFlightForm = ref(false);

const flightForm = useForm({
    origin_airport_id: 0,
    destination_airport_id: 0,
    flight_number: '',
    departure_at: '',
    arrival_at: '',
    airline: '',
});

watch(
    () => flightForm.departure_at,
    (departure) => {
        if (!departure) {
            return;
        }

        if (
            !flightForm.arrival_at ||
            flightForm.arrival_at < departure
        ) {
            flightForm.arrival_at = departure;
        }
    },
);

const originSearch = ref('');
const destinationSearch = ref('');

const originResults = ref<AirportSearchResult[]>([]);
const destinationResults = ref<AirportSearchResult[]>([]);

const searchAirports = async (
    query: string,
    type: 'origin' | 'destination'
) => {
    if (query.trim().length < 2) {
        if (type === 'origin') {
            originResults.value = [];
        } else {
            destinationResults.value = [];
        }

        return;
    }

    try {
        const response = await fetch(
            `/airports/search?q=${encodeURIComponent(query)}`
        );

        const data = await response.json();

        if (type === 'origin') {
            originResults.value = data.results ?? [];
        } else {
            destinationResults.value = data.results ?? [];
        }
    } catch (error) {
        console.error('Error searching airports:', error);

        if (type === 'origin') {
            originResults.value = [];
        } else {
            destinationResults.value = [];
        }
    }
};

const selectOrigin = (airport: AirportSearchResult) => {
    flightForm.origin_airport_id = airport.id;
    originSearch.value = formatAirport(airport);
    originResults.value = [];
};

const selectDestination = (airport: AirportSearchResult) => {
    flightForm.destination_airport_id = airport.id;
    destinationSearch.value = formatAirport(airport);
    destinationResults.value = [];
};

const resetFlightForm = () => {
    flightForm.reset();

    flightForm.departure_at = tripStartDateTime.value;
    flightForm.arrival_at = tripStartDateTime.value;

    originSearch.value = '';
    destinationSearch.value = '';

    originResults.value = [];
    destinationResults.value = [];
};

const cancelFlightForm = () => {
    showFlightForm.value = false;
    resetFlightForm();
};

const submitFlight = () => {
    flightForm.post(`/trips/${props.trip.id}/flights`, {
        preserveScroll: true,

        onSuccess: () => {
            showFlightForm.value = false;
            resetFlightForm();
        },
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

const tripStartDateTime = computed(() => {
    if (!props.trip.start_date) {
        return '';
    }

    return `${props.trip.start_date}T00:00`;
});


/*
|--------------------------------------------------------------------------
| PHOTOS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| PHOTOS
|--------------------------------------------------------------------------
*/

const photoInput = ref<HTMLInputElement | null>(null);

const isPhotosHovered = ref(false);
const isUploadingPhotos = ref(false);
const photoUploadMessage = ref('');
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
                only: ['photos'],
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
                (attachedList) => attachedList.id === list.id
            )
    )
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
        }
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
        a.country.localeCompare(b.country)
    );
});

const cityCount = computed(() => {
    return new Set(
        props.visits.map((visit) => visit.city.id)
    ).size;
});

const countryCount = computed(() => {
    return destinationsByCountry.value.length;
});

const flightCount = computed(() => props.flights.length);

const tripDays = computed(() => {
    if (!props.trip.start_date || !props.trip.end_date) {
        return null;
    }

    const start = new Date(props.trip.start_date);
    const end = new Date(props.trip.end_date);

    const difference =
        end.getTime() - start.getTime();

    return Math.round(
        difference / (1000 * 60 * 60 * 24)
    ) + 1;
});

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
    }).format(new Date(date));
};

const formatTime = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
};

const formatAirport = (airport: AirportSearchResult) => {
    const code = airport.iata_code || airport.icao_code || '';

    return `${code} — ${airport.city ?? airport.name}`;
};

</script>

<template>
    <AppLayout :title="trip.name">

        <div class="vyamap-page">

            <div
                class="relative mx-auto max-w-[1500px] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >

                <!-- =====================================================
                     TRIP HEADER
                ====================================================== -->

                <section class="vyamap-section mb-7">

                    <div class="vyamap-card-lg p-7 sm:p-9 lg:p-10">

                        <div
                            class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
                        >

                            <!-- Trip information -->

                            <div>

                                <div class="mb-4 flex items-center gap-3 vyamap-eyebrow">

                                    <span>
                                        {{ formatDateShort(trip.start_date) }}
                                    </span>

                                    <span class="text-white/15">
                                        —
                                    </span>

                                    <span>
                                        {{ formatDateShort(trip.end_date) }}
                                    </span>

                                </div>


                                <h1
                                    class="max-w-4xl text-5xl font-semibold tracking-[-0.065em] sm:text-6xl lg:text-7xl"
                                >
                                    {{ trip.name }}
                                </h1>


                                <p
                                    v-if="trip.description"
                                    class="mt-4 max-w-2xl text-sm leading-6 text-white/40 sm:text-base"
                                >
                                    {{ trip.description }}
                                </p>

                            </div>


                            <!-- Statistics -->

                            <div
                                class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:min-w-[460px]"
                            >

                                <div class="vyamap-stat">

                                    <div class="text-2xl font-semibold">
                                        {{ tripDays ?? '—' }}
                                    </div>

                                    <div class="mt-1 vyamap-eyebrow">
                                        Days
                                    </div>

                                </div>


                                <div class="vyamap-stat">

                                    <div class="text-2xl font-semibold">
                                        {{ cityCount }}
                                    </div>

                                    <div class="mt-1 vyamap-eyebrow">
                                        Cities
                                    </div>

                                </div>


                                <div class="vyamap-stat">

                                    <div class="text-2xl font-semibold">
                                        {{ countryCount }}
                                    </div>

                                    <div class="mt-1 vyamap-eyebrow">
                                        Countries
                                    </div>

                                </div>


                                <div class="vyamap-stat">

                                    <div class="text-2xl font-semibold">
                                        {{ flightCount }}
                                    </div>

                                    <div class="mt-1 vyamap-eyebrow">
                                        Flights
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <!-- =====================================================
                     MAP + PHOTOS + DESTINATIONS
                ====================================================== -->

                <section
                    class="mb-7 grid gap-7 lg:grid-cols-[1.75fr_0.85fr]"
                >

                    <!-- MAIN MAP -->

                    <div class="vyamap-map-card min-h-[530px]">

                        <div class="h-[530px] w-full">

                            <TripMap
                                :visits="visits"
                                :flights="[]"
                                :interactive="false"
                            />

                        </div>

                    </div>


                    <!-- RIGHT COLUMN -->

                    <div class="grid gap-7 sm:grid-cols-2 lg:grid-cols-1">

                        <!-- PHOTOS -->

                        <div class="vyamap-memory group">
                            <div class="vyamap-memory-content">
                                <!-- HEADER -->
                                <div class="flex items-center justify-between">
                                    <div class="vyamap-memory-label">
                                        Memories
                                    </div>

                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/[0.08] text-white/60 backdrop-blur-xl transition hover:bg-white/[0.12] hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="isUploadingPhotos"
                                        @click="openPhotoPicker"
                                    >
                                        <span v-if="!isUploadingPhotos">+</span>

                                        <span
                                            v-else
                                            class="h-3.5 w-3.5 animate-spin rounded-full border border-white/20 border-t-white"
                                        ></span>
                                    </button>
                                </div>

                                <div
                                    v-if="photoUploadMessage"
                                    class="mt-4 rounded-xl border border-white/10 bg-white/[0.05] px-4 py-3 text-xs text-white/60 transition-all"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            v-if="isUploadingPhotos"
                                            class="h-3.5 w-3.5 shrink-0 animate-spin rounded-full border border-white/20 border-t-white"
                                        ></span>

                                        <span
                                            v-else-if="photoUploadSuccess"
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-black"
                                        >
                                            ✓
                                        </span>

                                        <span>
                                            {{ photoUploadMessage }}
                                        </span>
                                    </div>
                                </div>
                                
                                <input
                                    ref="photoInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                                    multiple
                                    class="hidden"
                                    @change="handlePhotoSelection"
                                />

                                <!-- PHOTOS -->
                                <div
                                    v-if="photos.length"
                                    class="relative mt-5 h-[300px] overflow-hidden rounded-2xl"
                                    @mouseenter="isPhotosHovered = true"
                                    @mouseleave="isPhotosHovered = false"
                                >
                                    <div
                                        v-for="(photo, index) in photos.slice(0, 3)"
                                        :key="photo.id"
                                        class="absolute left-1/2 top-1/2 aspect-square w-[210px] overflow-hidden rounded-[22px] border-[6px] border-white bg-white shadow-2xl transition-transform duration-500 ease-out"
                                        :style="{
                                            zIndex: 10 - index,
                                            transform: `
                                                translate(-50%, -50%)
                                                translateX(${
                                                    isPhotosHovered
                                                        ? [-75, 0, 75][index]
                                                        : [-50, 0, 50][index]
                                                }px)
                                                rotate(${[-7, 2, 8][index]}deg)
                                            `,
                                        }"
                                    >
                                        <img
                                            :src="`/storage/${photo.thumbnail_path ?? photo.path}`"
                                            :alt="photo.original_filename"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                    </div>

                                    <!-- VIEW MORE -->
                                    <a
                                        :href="`/trips/${props.trip.id}/photos`"
                                        class="absolute left-1/2 top-1/2 z-20 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/10 bg-white/[0.92] px-7 py-3 text-sm font-semibold tracking-[-0.02em] text-black shadow-xl backdrop-blur-xl transition-all duration-300 hover:scale-105 hover:bg-white"
                                    >
                                        View More
                                    </a>
                                </div>

                                <!-- EMPTY STATE -->
                                <div
                                    v-else
                                    class="mt-5 rounded-2xl border border-dashed border-white/[0.08] bg-white/[0.02] px-5 py-8 text-center"
                                >
                                    <div class="text-2xl text-white/15">
                                        +
                                    </div>

                                    <p class="mt-2 text-xs text-white/30">
                                        Your visual story will live here.
                                    </p>

                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/[0.08] text-white/60 backdrop-blur-xl transition hover:bg-white/[0.12] hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="isUploadingPhotos"
                                        @click="openPhotoPicker"
                                    >
                                        <span v-if="!isUploadingPhotos">
                                            +
                                        </span>

                                        <span
                                            v-else
                                            class="h-3.5 w-3.5 animate-spin rounded-full border border-white/20 border-t-white"
                                        ></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        
                        <!-- DESTINATIONS -->
                         
                        <div class="vyamap-card-lg p-6 sm:p-7">

                            <div class="mb-5 vyamap-section-title">
                                Destinations
                            </div>


                            <div
                                v-if="destinationsByCountry.length"
                                class="space-y-5"
                            >

                                <div
                                    v-for="group in destinationsByCountry"
                                    :key="group.iso_code"
                                >

                                    <div class="vyamap-destination">

                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center text-2xl leading-none"
                                            :title="group.country"
                                        >
                                            {{ flagEmoji(group.iso_code) }}
                                        </div>


                                        <div class="min-w-0">

                                            <div class="truncate text-sm font-medium">
                                                {{ group.country }}
                                            </div>


                                            <div
                                                class="mt-0.5 text-[10px] text-white/25"
                                            >
                                                {{ group.cities.join(' · ') }}
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <div
                                v-else
                                class="text-sm text-white/25"
                            >
                                No destinations yet.
                            </div>

                        </div>

                    </div>

                </section>


                <!-- =====================================================
                     ITINERARY + FLIGHTS
                ====================================================== -->

                <section
                    class="mb-7 grid gap-7 lg:grid-cols-[0.85fr_1.15fr]"
                >

                    <!-- ITINERARY -->

                    <div class="vyamap-card-lg p-6 sm:p-8">

                        <div class="mb-7 flex items-start justify-between">

                            <div>

                                <div class="vyamap-section-title">
                                    Journey
                                </div>

                                <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                    Itinerary
                                </h2>

                            </div>


                            <!-- Add city -->

                            <div class="relative">

                                <input
                                    v-model="citySearch"
                                    type="text"
                                    placeholder="+"
                                    aria-label="Add city"
                                    class="h-9 w-9 cursor-text rounded-full border border-white/10 bg-white/[0.05] text-center text-sm text-white outline-none transition placeholder:text-white/30 focus:w-36 focus:px-4 focus:text-left"
                                    @input="searchCities"
                                />


                                <div
                                    v-if="cityResults.length"
                                    class="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-2xl border border-white/10 bg-[#11171d] shadow-2xl"
                                >

                                    <button
                                        v-for="city in cityResults"
                                        :key="`${city.source}-${city.id ?? city.external_id ?? city.name}`"
                                        type="button"
                                        class="block w-full px-4 py-3 text-left transition hover:bg-white/[0.06]"
                                        @click="addCityToTrip(city)"
                                    >

                                        <div class="text-sm font-medium">
                                            {{ city.name }}
                                        </div>

                                        <div class="mt-1 text-xs text-white/30">
                                            {{ city.country }}
                                            ·
                                            {{ city.iso_code }}
                                        </div>

                                    </button>

                                </div>

                            </div>

                            

                        </div>


                        <!-- Timeline -->

                        <div
                            v-if="timeline.length"
                            class="space-y-0"
                        >

                            <div
                                v-for="(visit, index) in timeline"
                                :key="visit.id"
                                class="relative pb-7 pl-12 last:pb-0"
                            >

                                <div
                                    v-if="index < timeline.length - 1"
                                    class="vyamap-timeline-line"
                                />


                                <div class="vyamap-timeline-node">
                                    {{ String(index + 1).padStart(2, '0') }}
                                </div>


                                <div>

                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >

                                        <div>

                                            <div class="vyamap-eyebrow">

                                                {{ formatDate(visit.visited_from) }}

                                                <span
                                                    v-if="visit.visited_until"
                                                    class="mx-1"
                                                >
                                                    —
                                                </span>

                                                <span v-if="visit.visited_until">
                                                    {{ formatDate(visit.visited_until) }}
                                                </span>

                                            </div>


                                            <div class="mt-2 flex items-center gap-2">

                                                <h3
                                                    class="text-xl font-semibold tracking-[-0.035em]"
                                                >
                                                    {{ visit.city.name }}
                                                </h3>


                                                <span
                                                    class="rounded-full border border-white/10 bg-white/[0.05] px-2 py-0.5 text-[8px] font-semibold text-white/40"
                                                >
                                                    {{ visit.city.iso_code }}
                                                </span>

                                            </div>


                                            <div
                                                class="mt-1 text-xs text-white/30"
                                            >
                                                {{ visit.city.country }}
                                            </div>

                                        </div>


                                        <button
                                            v-if="editingVisitId !== visit.id"
                                            type="button"
                                            class="text-[10px] text-white/25 transition hover:text-white"
                                            @click="startEditingVisit(visit)"
                                        >
                                            Edit
                                        </button>

                                    </div>


                                    <!-- Notes -->

                                    <p
                                        v-if="editingVisitId !== visit.id && visit.notes"
                                        class="mt-4 text-sm leading-6 text-white/35"
                                    >
                                        {{ visit.notes }}
                                    </p>


                                    <!-- Edit visit -->

                                    <form
                                        v-if="editingVisitId === visit.id"
                                        class="mt-5 rounded-2xl border border-white/10 bg-black/15 p-4"
                                        @submit.prevent="saveVisit(visit)"
                                    >

                                        <div class="grid gap-3 sm:grid-cols-2">

                                            <div>

                                                <label class="vyamap-label">
                                                    From
                                                </label>

                                                <input
                                                    v-model="editVisitForm.visited_from"
                                                    type="date"
                                                    :min="trip.start_date ?? undefined"
                                                    :max="trip.end_date ?? undefined"
                                                    class="vyamap-input"
                                                />

                                            </div>


                                            <div>

                                                <label class="vyamap-label">
                                                    Until
                                                </label>

                                                <input
                                                    v-model="editVisitForm.visited_until"
                                                    type="date"
                                                    :min="editVisitForm.visited_from || trip.start_date || undefined"
                                                    :max="trip.end_date ?? undefined"
                                                    class="vyamap-input"
                                                />

                                            </div>

                                        </div>


                                        <label class="vyamap-label mt-3">
                                            Notes
                                        </label>

                                        <textarea
                                            v-model="editVisitForm.notes"
                                            rows="4"
                                            placeholder="What did you see?"
                                            class="vyamap-textarea"
                                        />


                                        <div class="mt-3 flex gap-2">

                                            <button
                                                type="submit"
                                                :disabled="editVisitForm.processing"
                                                class="vyamap-button"
                                            >
                                                Save
                                            </button>


                                            <button
                                                type="button"
                                                class="vyamap-button-secondary"
                                                @click="cancelEditingVisit"
                                            >
                                                Cancel
                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>


                        <div
                            v-else
                            class="vyamap-empty"
                        >

                            <p class="text-sm text-white/25">
                                Add cities to build your itinerary.
                            </p>

                        </div>

                    </div>


                    <!-- FLIGHTS -->

                    <div class="grid gap-7">

                        <!-- FLIGHT MAP -->

                        <div class="vyamap-map-card min-h-[330px]">

                            <div class="h-[330px] w-full">

                                <TripMap
                                    :visits="[]"
                                    :flights="flights"
                                />

                            </div>

                        </div>


                        <!-- FLIGHT INFORMATION -->

                        <div class="vyamap-card-lg p-5 sm:p-6">

                            <div class="mb-4 flex items-center justify-between">

                                <div>

                                    <div class="vyamap-section-title">
                                        Transport
                                    </div>

                                    <h2
                                        class="mt-1 text-xl font-semibold tracking-[-0.04em]"
                                    >
                                        Flight information
                                    </h2>

                                </div>


                                <button
                                    v-if="!showFlightForm"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/[0.05] text-base text-white/50 transition hover:bg-white/[0.1] hover:text-white"
                                    @click="showFlightForm = true"
                                >
                                    +
                                </button>

                            </div>


                            <div
                                v-if="flights.length"
                                class="space-y-2"
                            >

                                <div
                                    v-for="flight in flights"
                                    :key="flight.id"
                                    class="vyamap-flight"
                                >

                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >

                                        <!-- Flight identity -->

                                        <div class="flex items-center gap-3">

                                            <div>

                                                <div class="text-sm font-semibold">
                                                    {{ flight.flight_number }}
                                                </div>

                                                <div
                                                    class="mt-0.5 text-[10px] text-white/30"
                                                >
                                                    {{ flight.airline || 'Flight' }}
                                                </div>

                                            </div>


                                            <div
                                                class="h-7 w-px bg-white/[0.08]"
                                            />


                                            <!-- Route -->

                                            <div class="text-xs text-white/60">

                                                {{ flight.origin.city }}

                                                <span class="mx-1 text-white/20">
                                                    →
                                                </span>

                                                {{ flight.destination.city }}

                                            </div>

                                        </div>


                                        <!-- Date / time -->

                                        <div
                                            class="flex items-center gap-4 text-right"
                                        >

                                            <div>

                                                <div class="vyamap-eyebrow">
                                                    Departure
                                                </div>

                                                <div
                                                    class="mt-0.5 text-xs text-white/55"
                                                >
                                                    {{ formatDateShort(flight.departure) }}
                                                    ·
                                                    {{ formatTime(flight.departure) }}
                                                </div>

                                            </div>


                                            <div class="text-white/15">
                                                →
                                            </div>


                                            <div>

                                                <div class="vyamap-eyebrow">
                                                    Arrival
                                                </div>

                                                <div
                                                    class="mt-0.5 text-xs text-white/55"
                                                >
                                                    {{ formatDateShort(flight.arrival) }}
                                                    ·
                                                    {{ formatTime(flight.arrival) }}
                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <div
                                v-else
                                class="vyamap-empty"
                            >

                                <p class="text-sm text-white/25">
                                    No flights yet.
                                </p>

                            </div>

                        </div>

                    </div>

                </section>


                <!-- =====================================================
                     FLIGHT FORM
                ====================================================== -->

                <section
                    v-if="showFlightForm"
                    class="vyamap-card-lg mb-7 p-6 sm:p-8"
                >

                    <div class="mb-6">

                        <div class="vyamap-section-title">
                            Transport
                        </div>

                        <h2 class="vyamap-section-heading">
                            Add flight
                        </h2>

                    </div>


                    <form
                        class="space-y-4"
                        @submit.prevent="submitFlight"
                    >

                        <!-- Airports -->

                        <div class="grid gap-4 md:grid-cols-2">

                            <div class="relative">

                                <label class="vyamap-label">
                                    Origin airport
                                </label>

                                <input
                                    v-model="originSearch"
                                    type="text"
                                    placeholder="Madrid, MAD..."
                                    class="vyamap-input"
                                    @input="searchAirports(originSearch, 'origin')"
                                />


                                <div
                                    v-if="originResults.length"
                                    class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-white/10 bg-[#11171d] shadow-2xl"
                                >

                                    <button
                                        v-for="airport in originResults"
                                        :key="airport.id"
                                        type="button"
                                        class="block w-full px-4 py-3 text-left transition hover:bg-white/[0.06]"
                                        @click="selectOrigin(airport)"
                                    >

                                        <div class="text-sm">

                                            {{ airport.iata_code || airport.icao_code }}

                                            ·

                                            {{ airport.city }}

                                        </div>

                                        <div class="mt-1 text-xs text-white/30">
                                            {{ airport.name }}
                                        </div>

                                    </button>

                                </div>

                            </div>


                            <div class="relative">

                                <label class="vyamap-label">
                                    Destination airport
                                </label>

                                <input
                                    v-model="destinationSearch"
                                    type="text"
                                    placeholder="Skopje, SKP..."
                                    class="vyamap-input"
                                    @input="searchAirports(destinationSearch, 'destination')"
                                />


                                <div
                                    v-if="destinationResults.length"
                                    class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-white/10 bg-[#11171d] shadow-2xl"
                                >

                                    <button
                                        v-for="airport in destinationResults"
                                        :key="airport.id"
                                        type="button"
                                        class="block w-full px-4 py-3 text-left transition hover:bg-white/[0.06]"
                                        @click="selectDestination(airport)"
                                    >

                                        <div class="text-sm">

                                            {{ airport.iata_code || airport.icao_code }}

                                            ·

                                            {{ airport.city }}

                                        </div>

                                        <div class="mt-1 text-xs text-white/30">
                                            {{ airport.name }}
                                        </div>

                                    </button>

                                </div>

                            </div>

                        </div>


                        <!-- Flight information -->

                        <div class="grid gap-4 md:grid-cols-2">

                            <div>

                                <label class="vyamap-label">
                                    Flight number
                                </label>

                                <input
                                    v-model="flightForm.flight_number"
                                    type="text"
                                    placeholder="Flight number"
                                    class="vyamap-input"
                                />

                            </div>


                            <div>

                                <label class="vyamap-label">
                                    Airline
                                </label>

                                <input
                                    v-model="flightForm.airline"
                                    type="text"
                                    placeholder="Airline"
                                    class="vyamap-input"
                                />

                            </div>

                        </div>


                        <!-- Date / time -->

                        <div class="grid gap-4 md:grid-cols-2">

                            <div>

                                <label class="vyamap-label">
                                    Departure
                                </label>

                                <input
                                    v-model="flightForm.departure_at"
                                    type="datetime-local"
                                    :min="`${trip.start_date}T00:00`"
                                    :max="`${trip.end_date}T23:59`"
                                    class="vyamap-input"
                                />

                            </div>


                            <div>

                                <label class="vyamap-label">
                                    Arrival
                                </label>

                                <input
                                    v-model="flightForm.arrival_at"
                                    type="datetime-local"
                                    :min="flightForm.departure_at || `${trip.start_date}T00:00`"
                                    :max="`${trip.end_date}T23:59`"
                                    class="vyamap-input"
                                />

                            </div>

                        </div>


                        <!-- Actions -->

                        <div class="flex gap-2">

                            <button
                                type="submit"
                                :disabled="flightForm.processing"
                                class="vyamap-button"
                            >
                                Add flight
                            </button>

                            <button
                                type="button"
                                class="vyamap-button-secondary"
                                @click="cancelFlightForm"
                            >
                                Cancel
                            </button>

                        </div>

                    </form>

                </section>


                <!-- =====================================================
                     GOOGLE MAPS
                ====================================================== -->

                <section class="vyamap-section">
                    <div class="vyamap-card-lg p-7 sm:p-8">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <div class="vyamap-section-title">
                                    Planning
                                </div>

                                <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                    Google Maps
                                </h2>

                                <p class="mt-2 max-w-xl text-sm text-white/30">
                                    Maps and lists used while planning the trip.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="vyamap-button-secondary"
                                @click="
                                    showGoogleMapForm = !showGoogleMapForm;
                                    googleMapMode = 'create';
                                "
                            >
                                + Add map
                            </button>
                        </div>

                        <div
                            v-if="showGoogleMapForm"
                            class="mt-7 rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5"
                        >
                            <div class="flex rounded-xl border border-white/[0.06] bg-black/20 p-1">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg px-4 py-2.5 text-xs font-medium transition"
                                    :class="
                                        googleMapMode === 'create'
                                            ? 'bg-white text-black'
                                            : 'text-white/40 hover:text-white'
                                    "
                                    @click="googleMapMode = 'create'"
                                >
                                    Create new list
                                </button>

                                <button
                                    type="button"
                                    class="flex-1 rounded-lg px-4 py-2.5 text-xs font-medium transition"
                                    :class="
                                        googleMapMode === 'existing'
                                            ? 'bg-white text-black'
                                            : 'text-white/40 hover:text-white'
                                    "
                                    @click="googleMapMode = 'existing'"
                                >
                                    Use existing list
                                </button>
                            </div>

                            <form
                                v-if="googleMapMode === 'create'"
                                class="mt-6"
                                @submit.prevent="submitGoogleMap"
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="vyamap-label">
                                            List name
                                        </label>

                                        <input
                                            v-model="googleMapForm.name"
                                            type="text"
                                            placeholder="Italy"
                                            class="vyamap-input"
                                        />

                                        <p
                                            v-if="googleMapForm.errors.name"
                                            class="mt-2 text-xs text-red-400"
                                        >
                                            {{ googleMapForm.errors.name }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="vyamap-label">
                                            Google Maps URL
                                        </label>

                                        <input
                                            v-model="googleMapForm.url"
                                            type="url"
                                            placeholder="https://maps.app.goo.gl/..."
                                            class="vyamap-input"
                                        />

                                        <p
                                            v-if="googleMapForm.errors.url"
                                            class="mt-2 text-xs text-red-400"
                                        >
                                            {{ googleMapForm.errors.url }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 flex gap-2">
                                    <button
                                        type="submit"
                                        class="vyamap-button"
                                        :disabled="googleMapForm.processing"
                                    >
                                        Add list
                                    </button>

                                    <button
                                        type="button"
                                        class="vyamap-button-secondary"
                                        @click="cancelGoogleMapForm"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>

                            <div
                                v-else
                                class="mt-6"
                            >
                                <div v-if="availableGoogleMapLists.length">
                                    <label class="vyamap-label">
                                        Select a Google Maps list
                                    </label>

                                    <select
                                        v-model="selectedGoogleMapListId"
                                        class="vyamap-input"
                                    >
                                        <option :value="null">
                                            Select a list...
                                        </option>

                                        <option
                                            v-for="mapList in availableGoogleMapLists"
                                            :key="mapList.id"
                                            :value="mapList.id"
                                        >
                                            {{ mapList.name }}
                                        </option>
                                    </select>

                                    <div class="mt-5 flex gap-2">
                                        <button
                                            type="button"
                                            class="vyamap-button"
                                            :disabled="!selectedGoogleMapListId"
                                            @click="attachExistingGoogleMap"
                                        >
                                            Add list
                                        </button>

                                        <button
                                            type="button"
                                            class="vyamap-button-secondary"
                                            @click="cancelGoogleMapForm"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-5"
                                >
                                    <p class="text-xs text-white/30">
                                        You don't have any other Google Maps lists available.
                                    </p>

                                    <button
                                        type="button"
                                        class="mt-4 text-xs font-medium text-white/60 transition hover:text-white"
                                        @click="googleMapMode = 'create'"
                                    >
                                        + Create a new list
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="props.google_map_lists.length"
                            class="mt-7 grid gap-3"
                        >
                            <div
                                v-for="mapList in props.google_map_lists"
                                :key="mapList.id"
                                class="flex items-center justify-between gap-4 rounded-2xl border border-white/[0.08] bg-white/[0.025] p-4"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <span class="text-lg">
                                            ↗
                                        </span>

                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-medium text-white">
                                                {{ mapList.name }}
                                            </div>

                                            <div class="mt-1 truncate text-xs text-white/25">
                                                Google Maps list
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <a
                                        :href="mapList.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-full border border-white/[0.08] bg-white/[0.04] px-4 py-2 text-[10px] font-medium text-white/60 transition hover:bg-white/[0.08] hover:text-white"
                                    >
                                        Open
                                    </a>

                                    <button
                                        type="button"
                                        class="rounded-full px-3 py-2 text-[10px] text-white/25 transition hover:text-red-400"
                                        @click="router.delete(`/trips/${props.trip.id}/google-maps/${mapList.id}`, { preserveScroll: true })"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="!showGoogleMapForm"
                            class="vyamap-empty mt-7"
                        >
                            <div class="text-center">
                                <div class="text-2xl text-white/10">
                                    ↗
                                </div>

                                <p class="mt-2 text-xs text-white/25">
                                    No Google Maps lists linked to this trip yet.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

            </div>

        </div>

    </AppLayout>
</template>