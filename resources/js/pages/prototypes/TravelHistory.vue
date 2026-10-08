<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import { computed, ref } from 'vue';

interface City {
    name: string;
    country: string;
    countryCode: string;
}

interface Country {
    name: string;
    code: string;
    cities: City[];
    trips: number;
    cover: string;
}

interface Trip {
    name: string;
    country: string;
    dates: string;
    duration: string;
    cities: string[];
    cover: string;
}

const activeYear = ref<'all' | 2026 | 2025 | 2024>('all');

const countries: Country[] = [
    {
        name: 'Albania',
        code: 'AL',
        trips: 1,
        cities: [
            {
                name: 'Skopje',
                country: 'North Macedonia',
                countryCode: 'MK',
            },
            {
                name: 'Pristina',
                country: 'Kosovo',
                countryCode: 'XK',
            },
            {
                name: 'Ohrid',
                country: 'North Macedonia',
                countryCode: 'MK',
            },
            {
                name: 'Tirana',
                country: 'Albania',
                countryCode: 'AL',
            },
            {
                name: 'Sarandë',
                country: 'Albania',
                countryCode: 'AL',
            },
        ],
        cover: 'https://images.unsplash.com/photo-1539650116574-75c0c6d73f6e?auto=format&fit=crop&w=1400&q=85',
    },
    {
        name: 'Italy',
        code: 'IT',
        trips: 3,
        cities: [
            {
                name: 'Rome',
                country: 'Italy',
                countryCode: 'IT',
            },
            {
                name: 'Milan',
                country: 'Italy',
                countryCode: 'IT',
            },
            {
                name: 'Como',
                country: 'Italy',
                countryCode: 'IT',
            },
            {
                name: 'Venice',
                country: 'Italy',
                countryCode: 'IT',
            },
        ],
        cover: 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=1400&q=85',
    },
    {
        name: 'Croatia',
        code: 'HR',
        trips: 1,
        cities: [
            {
                name: 'Dubrovnik',
                country: 'Croatia',
                countryCode: 'HR',
            },
            {
                name: 'Split',
                country: 'Croatia',
                countryCode: 'HR',
            },
            {
                name: 'Zagreb',
                country: 'Croatia',
                countryCode: 'HR',
            },
        ],
        cover: 'https://images.unsplash.com/photo-1555990538-1f7d9b9f0a52?auto=format&fit=crop&w=1400&q=85',
    },
];

const trips: Trip[] = [
    {
        name: 'Albania',
        country: 'Albania',
        dates: '26 Aug — 2 Sep 2026',
        duration: '8 days',
        cities: ['Skopje', 'Pristina', 'Ohrid', 'Tirana', 'Sarandë'],
        cover: 'https://images.unsplash.com/photo-1539650116574-75c0c6d73f6e?auto=format&fit=crop&w=1600&q=90',
    },
    {
        name: 'Italy · Summer',
        country: 'Italy',
        dates: '12 Jul — 20 Jul 2026',
        duration: '9 days',
        cities: ['Rome', 'Milan', 'Como'],
        cover: 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=1600&q=90',
    },
    {
        name: 'Croatia',
        country: 'Croatia',
        dates: '18 Jun — 25 Jun 2025',
        duration: '8 days',
        cities: ['Dubrovnik', 'Split', 'Zagreb'],
        cover: 'https://images.unsplash.com/photo-1555990538-1f7d9b9f0a52?auto=format&fit=crop&w=1600&q=90',
    },
    {
        name: 'Italy · Spring',
        country: 'Italy',
        dates: '4 Apr — 9 Apr 2025',
        duration: '6 days',
        cities: ['Venice', 'Milan'],
        cover: 'https://images.unsplash.com/photo-1523906834658-6e24ef2386f9?auto=format&fit=crop&w=1600&q=90',
    },
];

const yearOptions = [
    { label: 'All years', value: 'all' },
    { label: '2026', value: 2026 },
    { label: '2025', value: 2025 },
    { label: '2024', value: 2024 },
];

const filteredTrips = computed(() => {
    if (activeYear.value === 'all') {
        return trips;
    }

    return trips.filter((trip) =>
        trip.dates.includes(String(activeYear.value)),
    );
});
</script>

<template>
    <AppLayout>
        <div class="min-h-screen bg-[#050505] text-white">
            <main class="mx-auto max-w-[1500px] px-5 pb-24 pt-10 sm:px-8 lg:px-12 lg:pt-14">
                <!-- Header -->
                <header class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="text-[10px] font-medium uppercase tracking-[0.24em] text-white/25">
                            Personal archive
                        </div>

                        <h1 class="mt-3 text-5xl font-semibold tracking-[-0.065em] sm:text-6xl lg:text-7xl">
                            Travel history
                        </h1>

                        <p class="mt-5 max-w-2xl text-sm leading-6 text-white/35">
                            Every place you've visited, every trip you've made.
                            One history, always growing.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-for="year in yearOptions"
                            :key="year.value"
                            type="button"
                            class="rounded-full border px-4 py-2 text-xs transition"
                            :class="
                                activeYear === year.value
                                    ? 'border-white bg-white text-black'
                                    : 'border-white/[0.08] bg-white/[0.025] text-white/40 hover:border-white/15 hover:text-white'
                            "
                            @click="activeYear = year.value as typeof activeYear"
                        >
                            {{ year.label }}
                        </button>
                    </div>
                </header>

                <!-- Stats -->
                <section class="mt-12 grid grid-cols-2 gap-px overflow-hidden rounded-3xl border border-white/[0.06] bg-white/[0.06] sm:grid-cols-4">
                    <div class="bg-[#090909] p-6 sm:p-7">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            18
                        </div>
                        <div class="mt-2 text-[10px] uppercase tracking-[0.18em] text-white/25">
                            Countries
                        </div>
                    </div>

                    <div class="bg-[#090909] p-6 sm:p-7">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            64
                        </div>
                        <div class="mt-2 text-[10px] uppercase tracking-[0.18em] text-white/25">
                            Cities
                        </div>
                    </div>

                    <div class="bg-[#090909] p-6 sm:p-7">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            27
                        </div>
                        <div class="mt-2 text-[10px] uppercase tracking-[0.18em] text-white/25">
                            Trips
                        </div>
                    </div>

                    <div class="bg-[#090909] p-6 sm:p-7">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            9
                        </div>
                        <div class="mt-2 text-[10px] uppercase tracking-[0.18em] text-white/25">
                            Years travelling
                        </div>
                    </div>
                </section>

                <!-- Map concept -->
                <section class="mt-8 overflow-hidden rounded-[2rem] border border-white/[0.06] bg-[#090909]">
                    <div class="relative h-[420px] overflow-hidden sm:h-[500px]">
                        <div
                            class="absolute inset-0 opacity-60"
                            style="
                                background-image:
                                    radial-gradient(circle at 20% 30%, rgba(255,255,255,0.08) 0 1px, transparent 1px),
                                    radial-gradient(circle at 70% 65%, rgba(255,255,255,0.06) 0 1px, transparent 1px);
                                background-size: 42px 42px, 57px 57px;
                            "
                        />

                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.06),transparent_55%)]" />

                        <div class="absolute left-[18%] top-[32%]">
                            <div class="h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_6px_rgba(255,255,255,0.08),0_0_30px_rgba(255,255,255,0.4)]" />
                        </div>

                        <div class="absolute left-[45%] top-[48%]">
                            <div class="h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_6px_rgba(255,255,255,0.08),0_0_30px_rgba(255,255,255,0.4)]" />
                        </div>

                        <div class="absolute left-[51%] top-[55%]">
                            <div class="h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_6px_rgba(255,255,255,0.08),0_0_30px_rgba(255,255,255,0.4)]" />
                        </div>

                        <div class="absolute left-[57%] top-[43%]">
                            <div class="h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_6px_rgba(255,255,255,0.08),0_0_30px_rgba(255,255,255,0.4)]" />
                        </div>

                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-[#090909] via-[#090909]/70 to-transparent px-7 pb-7 pt-24 sm:px-9">
                            <div class="text-[10px] uppercase tracking-[0.2em] text-white/25">
                                Places visited
                            </div>

                            <div class="mt-2 text-2xl font-medium tracking-[-0.04em]">
                                Europe
                            </div>

                            <div class="mt-1 text-xs text-white/30">
                                18 countries · 64 cities
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Countries -->
                <section class="mt-16">
                    <div class="flex items-end justify-between">
                        <div>
                            <div class="text-[10px] uppercase tracking-[0.2em] text-white/20">
                                Explore
                            </div>

                            <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                Countries
                            </h2>
                        </div>

                        <div class="text-xs text-white/25">
                            18 visited
                        </div>
                    </div>

                    <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="country in countries"
                            :key="country.code"
                            class="group overflow-hidden rounded-[1.75rem] border border-white/[0.06] bg-[#090909]"
                        >
                            <div class="relative h-56 overflow-hidden">
                                <img
                                    :src="country.cover"
                                    :alt="country.name"
                                    class="absolute inset-0 h-full w-full object-cover opacity-80 transition duration-700 group-hover:scale-105 group-hover:opacity-100"
                                />

                                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/10 to-transparent" />

                                <div class="absolute bottom-5 left-5 right-5">
                                    <div class="text-2xl font-medium tracking-[-0.04em]">
                                        {{ country.name }}
                                    </div>

                                    <div class="mt-1 text-xs text-white/45">
                                        {{ country.trips }}
                                        {{ country.trips === 1 ? 'trip' : 'trips' }}
                                    </div>
                                </div>
                            </div>

                            <div class="p-5">
                                <div class="text-[10px] uppercase tracking-[0.18em] text-white/20">
                                    Cities visited
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="city in country.cities"
                                        :key="city.name"
                                        class="rounded-full border border-white/[0.06] bg-white/[0.025] px-3 py-1.5 text-[11px] text-white/45"
                                    >
                                        {{ city.name }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- Trips -->
                <section class="mt-20">
                    <div class="flex items-end justify-between">
                        <div>
                            <div class="text-[10px] uppercase tracking-[0.2em] text-white/20">
                                Timeline
                            </div>

                            <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                Trips
                            </h2>
                        </div>

                        <div class="text-xs text-white/25">
                            {{ filteredTrips.length }} shown
                        </div>
                    </div>

                    <div class="mt-7 space-y-4">
                        <article
                            v-for="trip in filteredTrips"
                            :key="trip.name + trip.dates"
                            class="group relative overflow-hidden rounded-[1.75rem] border border-white/[0.06] bg-[#090909]"
                        >
                            <div class="grid lg:grid-cols-[360px_1fr]">
                                <div class="relative h-64 overflow-hidden lg:h-full lg:min-h-[270px]">
                                    <img
                                        :src="trip.cover"
                                        :alt="trip.name"
                                        class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"
                                    />

                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent lg:bg-gradient-to-r" />
                                </div>

                                <div class="flex flex-col justify-between p-6 sm:p-8">
                                    <div>
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="text-[10px] uppercase tracking-[0.18em] text-white/20">
                                                {{ trip.country }}
                                            </div>

                                            <div class="text-xs text-white/25">
                                                {{ trip.duration }}
                                            </div>
                                        </div>

                                        <h3 class="mt-3 text-3xl font-medium tracking-[-0.05em]">
                                            {{ trip.name }}
                                        </h3>

                                        <div class="mt-2 text-xs text-white/30">
                                            {{ trip.dates }}
                                        </div>
                                    </div>

                                    <div class="mt-8">
                                        <div class="text-[10px] uppercase tracking-[0.18em] text-white/20">
                                            Places
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                v-for="city in trip.cities"
                                                :key="city"
                                                class="rounded-full border border-white/[0.07] bg-white/[0.025] px-3 py-1.5 text-[11px] text-white/45"
                                            >
                                                {{ city }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </AppLayout>
</template>