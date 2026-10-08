<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import { computed, ref } from 'vue';

interface Trip {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
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

const props = defineProps<{
    trip: Trip;
    photos: Photo[];
}>();

const selectedPhoto = ref<Photo | null>(null);
const search = ref('');

const filteredPhotos = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.photos;
    }

    return props.photos.filter((photo) =>
        photo.original_filename.toLowerCase().includes(query),
    );
});

const photoUrl = (photo: Photo) => {
    return `/storage/${photo.thumbnail_path ?? photo.path}`;
};

const fullPhotoUrl = (photo: Photo) => {
    return `/storage/${photo.path}`;
};

const openPhoto = (photo: Photo) => {
    selectedPhoto.value = photo;
};

const closePhoto = () => {
    selectedPhoto.value = null;
};

const formatDate = (date: string | null) => {
    if (!date) {
        return null;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
};
</script>

<template>
    <AppLayout title="Trip Photos">
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
                                Travel memories
                            </div>

                            <div
                                class="mt-3 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="text-[10px] uppercase tracking-[0.22em] text-white/35"
                                    >
                                        Photos
                                    </div>

                                    <h1
                                        class="mt-2 text-3xl font-semibold tracking-[-0.05em] text-white sm:text-4xl"
                                    >
                                        {{ trip.name }}
                                    </h1>

                                    <div
                                        v-if="trip.start_date || trip.end_date"
                                        class="mt-3 text-xs text-white/30"
                                    >
                                        <span v-if="formatDate(trip.start_date)">
                                            {{ formatDate(trip.start_date) }}
                                        </span>

                                        <span
                                            v-if="trip.start_date && trip.end_date"
                                            class="mx-2"
                                        >
                                            →
                                        </span>

                                        <span v-if="formatDate(trip.end_date)">
                                            {{ formatDate(trip.end_date) }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="flex shrink-0 items-end gap-8 lg:gap-10"
                                >
                                    <div>
                                        <div
                                            class="text-[9px] uppercase tracking-[0.22em] text-white/25"
                                        >
                                            Photos
                                        </div>

                                        <div
                                            class="mt-1 text-2xl font-semibold tracking-[-0.04em] text-white"
                                        >
                                            {{ photos.length }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- TOOLBAR -->
                <section
                    v-if="photos.length"
                    class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div
                            class="text-[9px] uppercase tracking-[0.22em] text-white/25"
                        >
                            Trip memories
                        </div>

                        <h2
                            class="mt-1 text-lg font-medium tracking-[-0.03em] text-white"
                        >
                            All photos
                        </h2>
                    </div>

                    <div class="relative">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search photos..."
                            class="w-full rounded-full border border-white/[0.08] bg-white/[0.04] px-4 py-2.5 text-xs text-white outline-none placeholder:text-white/20 transition focus:border-white/[0.16] sm:w-[220px]"
                        />
                    </div>
                </section>

                <!-- PHOTOS -->
                <section v-if="filteredPhotos.length">
                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
                    >
                        <button
                            v-for="photo in filteredPhotos"
                            :key="photo.id"
                            type="button"
                            class="group relative aspect-square overflow-hidden rounded-[18px] border border-white/[0.07] bg-white/[0.03] text-left"
                            @click="openPhoto(photo)"
                        >
                            <img
                                :src="photoUrl(photo)"
                                alt="Travel photo"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                loading="lazy"
                            />

                            <div
                                class="pointer-events-none absolute inset-0 bg-black/0 transition duration-300 group-hover:bg-black/10"
                            ></div>
                        </button>
                    </div>
                </section>

                <!-- SEARCH EMPTY -->
                <section
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
                        No photos found
                    </h3>

                    <p
                        class="mx-auto mt-2 max-w-sm text-xs leading-5 text-white/30"
                    >
                        Try searching with another filename.
                    </p>
                </section>

                <!-- NO PHOTOS -->
                <section
                    v-else
                    class="rounded-[30px] border border-dashed border-white/[0.10] bg-white/[0.02] px-6 py-24 text-center"
                >
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-white/[0.08] bg-white/[0.03] text-xl text-white/30"
                    >
                        +
                    </div>

                    <h3
                        class="mt-5 text-lg font-medium tracking-[-0.03em] text-white"
                    >
                        No photos yet
                    </h3>

                    <p
                        class="mx-auto mt-2 max-w-sm text-xs leading-5 text-white/30"
                    >
                        Your travel memories will appear here once you start
                        adding photos to this trip.
                    </p>
                </section>
            </main>

            <!-- PHOTO VIEWER -->
            <Teleport to="body">
                <div
                    v-if="selectedPhoto"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 p-5 backdrop-blur-xl"
                    @click.self="closePhoto"
                >
                    <button
                        type="button"
                        class="absolute right-5 top-5 flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-xl text-white/50 transition hover:bg-white/10 hover:text-white"
                        @click="closePhoto"
                    >
                        ×
                    </button>

                    <div
                        class="flex max-h-[92vh] max-w-[94vw] items-center justify-center"
                    >
                        <img
                            :src="fullPhotoUrl(selectedPhoto)"
                            alt="Travel photo"
                            class="max-h-[88vh] max-w-[94vw] rounded-[20px] object-contain shadow-2xl"
                        />
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>