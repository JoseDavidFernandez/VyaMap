<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { tripPath } from '../../utils/routes';

interface Trip {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
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
    return photo.thumbnail_url;
};

const fullPhotoUrl = (photo: Photo) => {
    return photo.url;
};

const openPhoto = (photo: Photo) => {
    selectedPhoto.value = photo;
};

const closePhoto = () => {
    selectedPhoto.value = null;
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closePhoto();
    }
};

watch(selectedPhoto, (photo) => {
    if (photo) {
        window.addEventListener('keydown', onKeydown);
    } else {
        window.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

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
    <AppLayout :title="`Photos · ${trip.name}`">
        <div class="vyamap-container">
            <Link :href="tripPath(trip)" class="vyamap-button-ghost vyamap-button-sm -ml-3 mb-3">
                <Icon name="arrow-left" :size="16" />
                {{ trip.name }}
            </Link>

            <header class="vyamap-page-header">
                <div>
                    <h1 class="vyamap-page-title">Photos</h1>
                    <p class="vyamap-page-description">
                        {{ trip.name }}<template v-if="trip.start_date || trip.end_date"> ·
                            <span class="vyamap-number">{{ formatDate(trip.start_date) }}<template v-if="trip.start_date && trip.end_date"> – </template>{{ formatDate(trip.end_date) }}</span>
                        </template>
                        · <span class="vyamap-number">{{ photos.length }}</span> {{ photos.length === 1 ? 'photo' : 'photos' }}
                    </p>
                </div>

                <div v-if="photos.length" class="relative w-full sm:w-64">
                    <label for="trip-photo-search" class="sr-only">Search photos</label>
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--vyamap-text-subtle)]">
                        <Icon name="search" :size="18" />
                    </span>
                    <input
                        id="trip-photo-search"
                        v-model="search"
                        type="search"
                        placeholder="Search by file name..."
                        class="vyamap-input !pl-10"
                    />
                </div>
            </header>

            <ul v-if="filteredPhotos.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5">
                <li v-for="photo in filteredPhotos" :key="photo.id">
                    <button
                        type="button"
                        class="block aspect-square w-full overflow-hidden rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)] transition-colors hover:border-[rgba(34,211,238,0.5)]"
                        :aria-label="`Open ${photo.original_filename}`"
                        @click="openPhoto(photo)"
                    >
                        <img :src="photoUrl(photo)" :alt="photo.original_filename" class="h-full w-full object-cover" loading="lazy" />
                    </button>
                </li>
            </ul>

            <EmptyState
                v-else-if="search"
                icon="search"
                title="No photos found"
                text="Try searching with another filename."
            >
                <template #actions>
                    <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="search = ''">Clear search</button>
                </template>
            </EmptyState>

            <EmptyState
                v-else
                icon="photo"
                title="No photos yet"
                text="Your travel memories will appear here once you start adding photos to this trip."
            >
                <template #actions>
                    <Link :href="tripPath(trip)" class="vyamap-button-secondary vyamap-button-sm">Back to the trip</Link>
                </template>
            </EmptyState>
        </div>

        <!-- VISOR -->
        <Teleport to="body">
            <div
                v-if="selectedPhoto"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-[rgba(4,7,14,0.94)] p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="selectedPhoto.original_filename"
                @click.self="closePhoto"
            >
                <button
                    type="button"
                    class="vyamap-icon-button absolute right-4 top-4"
                    aria-label="Close photo"
                    @click="closePhoto"
                >
                    <Icon name="close" :size="20" />
                </button>

                <figure class="flex max-h-[92vh] max-w-[94vw] flex-col items-center gap-3">
                    <img
                        :src="fullPhotoUrl(selectedPhoto)"
                        :alt="selectedPhoto.original_filename"
                        class="max-h-[84vh] max-w-[94vw] rounded-[var(--vyamap-radius-lg)] object-contain"
                    />
                    <figcaption class="text-sm text-[var(--vyamap-text-muted)]">
                        {{ selectedPhoto.original_filename }}
                        <template v-if="formatDate(selectedPhoto.taken_at)"> · <span class="vyamap-number">{{ formatDate(selectedPhoto.taken_at) }}</span></template>
                    </figcaption>
                </figure>
            </div>
        </Teleport>
    </AppLayout>
</template>
