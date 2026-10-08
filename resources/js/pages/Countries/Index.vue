<script setup lang="ts">

import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

interface Country {
    id: number;
    name: string;
    iso_code: string;
    cities_count: number;
    trips_count: number;
}

const props = defineProps<{
    countries: Country[];
}>();

const search = ref('');

const flagEmoji = (isoCode: string) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(letter.charCodeAt(0) + 127397),
        )
        .join('');
};

const filteredCountries = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.countries;
    }

    return props.countries.filter((country) =>
        country.name.toLowerCase().includes(query),
    );
});

</script>

<template>

    <AppLayout title="Countries">

        <div class="vyamap-page">

            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >

                <!-- HEADER -->

                <section class="mb-8">

                    <div
                        class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-6 py-9 sm:px-10 sm:py-11 lg:px-12 lg:py-12"
                    >

                        <div
                            class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.05] blur-3xl"
                        ></div>

                        <div
                            class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.05] blur-3xl"
                        ></div>

                        <div class="relative z-10">

                            <div
                                class="text-[9px] uppercase tracking-[0.24em] text-white/25"
                            >
                                Your world
                            </div>

                            <div
                                class="mt-3 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                            >

                                <div>

                                    <h1
                                        class="text-4xl font-semibold leading-[0.95] tracking-[-0.065em] text-white sm:text-5xl lg:text-6xl"
                                    >
                                        Countries
                                    </h1>

                                    <p
                                        class="mt-5 max-w-xl text-sm leading-6 text-white/40 sm:text-base"
                                    >
                                        Every country you've visited, organised in one place.
                                    </p>

                                </div>

                                <div
                                    class="shrink-0 text-[10px] uppercase tracking-[0.18em] text-white/25"
                                >
                                    {{ props.countries.length }}
                                    {{
                                        props.countries.length === 1
                                            ? 'country'
                                            : 'countries'
                                    }}
                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <!-- TOOLBAR -->

                <section class="mb-7">

                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >

                        <div class="flex items-center gap-3">

                            <div
                                class="text-[9px] uppercase tracking-[0.24em] text-white/25"
                            >
                                Travel history
                            </div>

                            <div
                                class="h-3 w-px bg-white/[0.08]"
                            ></div>

                            <div class="text-xs text-white/30">
                                {{ filteredCountries.length }}
                            </div>

                        </div>

                        <div class="relative sm:w-[250px]">

                            <input
                                v-model="search"
                                type="search"
                                placeholder="Search countries..."
                                class="w-full rounded-full border border-white/[0.08] bg-white/[0.035] px-4 py-2.5 pr-10 text-xs text-white outline-none transition placeholder:text-white/20 focus:border-white/[0.16] focus:bg-white/[0.05]"
                            />

                            <div
                                class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm text-white/25"
                            >
                                ⌕
                            </div>

                        </div>

                    </div>

                </section>


                <!-- COUNTRIES -->
                <section>
                    <div
                        v-if="filteredCountries.length"
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                    >

                        <Link
                            v-for="country in filteredCountries"
                            :key="country.id"
                            :href="`/countries/${country.id}`"
                            class="group rounded-[22px] border border-white/[0.07] bg-white/[0.025] p-5 transition duration-300 hover:-translate-y-0.5 hover:border-white/[0.14] hover:bg-white/[0.045]"
                        >

                            <div class="flex items-center gap-4">

                                <div
                                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-white/[0.06] bg-white/[0.04] text-3xl"
                                >
                                    {{ flagEmoji(country.iso_code) }}
                                </div>

                                <div class="min-w-0 flex-1">

                                    <div
                                        class="truncate text-lg font-medium tracking-[-0.02em] text-white"
                                    >
                                        {{ country.name }}
                                    </div>

                                    <div
                                        class="mt-1 text-xs text-white/30"
                                    >
                                        {{ country.cities_count }}
                                        {{
                                            country.cities_count === 1
                                                ? 'city'
                                                : 'cities'
                                        }}
                                        ·
                                        {{ country.trips_count }}
                                        {{
                                            country.trips_count === 1
                                                ? 'trip'
                                                : 'trips'
                                        }}
                                    </div>

                                </div>

                                <div
                                    class="text-xl text-white/20 transition duration-300 group-hover:translate-x-1 group-hover:text-white/60"
                                >
                                    →
                                </div>

                            </div>

                        </Link>

                    </div>


                    <!-- SEARCH EMPTY -->
                    <div
                        v-else-if="search"
                        class="rounded-[30px] border border-dashed border-white/[0.10] bg-white/[0.02] px-6 py-24 text-center"
                    >

                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-white/[0.08] bg-white/[0.03] text-xl text-white/30"
                        >
                            ⌕
                        </div>

                        <h3
                            class="mt-5 text-lg font-medium tracking-[-0.03em] text-white"
                        >
                            No countries found
                        </h3>

                        <p
                            class="mx-auto mt-2 max-w-sm text-xs leading-5 text-white/30"
                        >
                            Try searching with another country name.
                        </p>

                    </div>


                    <!-- EMPTY -->
                    <div
                        v-else
                        class="rounded-[30px] border border-dashed border-white/[0.10] bg-white/[0.02] px-6 py-24 text-center"
                    >

                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-white/[0.08] bg-white/[0.03] text-xl text-white/30"
                        >
                            ◌
                        </div>

                        <h3
                            class="mt-5 text-lg font-medium tracking-[-0.03em] text-white"
                        >
                            No countries yet
                        </h3>

                        <p
                            class="mx-auto mt-2 max-w-sm text-xs leading-5 text-white/30"
                        >
                            Your visited countries will appear here once you start adding trips.
                        </p>

                    </div>

                </section>

            </main>

        </div>

    </AppLayout>

</template>