<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { stockImage } from '../../data/stockImages';

interface Photo {
    id: number;
    trip_id?: number | null;
    visit_id?: number | null;
    place_id?: number | null;
    journal_entry_id?: number | null;
    // URLs autenticadas (PhotoFileController); nunca rutas de disco.
    url: string;
    thumbnail_url: string;
    original_filename?: string | null;
    mime_type?: string | null;
    size?: number | null;
    width?: number | null;
    height?: number | null;
    taken_at?: string | null;
    latitude?: number | null;
    longitude?: number | null;
    processing_status?: string | null;
    albums?: Album[];
}

interface Album {
    id: number;
    name: string;
    description?: string | null;
    cover_photo_id?: number | null;
    photos_count?: number;
    cover_url?: string | null;
}

interface Trip {
    id: number;
    name: string;
    start_date?: string | null;
    end_date?: string | null;
}

const props = defineProps<{
    photos: Photo[];
    albums: Album[];
    trips: Trip[];
}>();

const search = ref('');
const selectedPhoto = ref<Photo | null>(null);
const selectedPhotos = ref<number[]>([]);

const showFilters = ref(false);
const showCreateAlbum = ref(false);

const activeAlbumId = ref<number | null>(null);
const activeTripId = ref<number | null>(null);

const dateFrom = ref('');
const dateTo = ref('');

const draggedPhotoId = ref<number | null>(null);
const draggedPhotoIds = ref<number[]>([]);
const dragOverAlbumId = ref<number | null>(null);
const isDragging = ref(false);

const newAlbumName = ref('');
const newAlbumDescription = ref('');

const isCreatingAlbum = ref(false);
const isAddingToAlbum = ref(false);
const isRemovingFromAlbum = ref(false);

const showDeleteConfirmation = ref(false);
const photosToDelete = ref<number[]>([]);
const isDeletingPhotos = ref(false);
const deleteError = ref('');

const activeAlbum = computed(() => {
    if (activeAlbumId.value === null) {
        return null;
    }

    return (
        props.albums.find((album) => album.id === activeAlbumId.value) ??
        null
    );
});

const filteredPhotos = computed(() => {
    let photos = [...props.photos];

    const query = search.value.trim().toLowerCase();

    if (query) {
        photos = photos.filter((photo) =>
            (photo.original_filename ?? '').toLowerCase().includes(query),
        );
    }

    if (activeAlbumId.value !== null) {
        photos = photos.filter((photo) =>
            photo.albums?.some(
                (album) => album.id === activeAlbumId.value,
            ),
        );
    }

    if (activeTripId.value !== null) {
        photos = photos.filter(
            (photo) => photo.trip_id === activeTripId.value,
        );
    }

    if (dateFrom.value) {
        const from = new Date(dateFrom.value);

        photos = photos.filter((photo) => {
            if (!photo.taken_at) {
                return false;
            }

            return new Date(photo.taken_at) >= from;
        });
    }

    if (dateTo.value) {
        const to = new Date(dateTo.value);
        to.setHours(23, 59, 59, 999);

        photos = photos.filter((photo) => {
            if (!photo.taken_at) {
                return false;
            }

            return new Date(photo.taken_at) <= to;
        });
    }

    return photos;
});

const photoUrl = (photo: Photo) => {
    return photo.thumbnail_url;
};

const fullPhotoUrl = (photo: Photo) => {
    return photo.url;
};

const albumCoverUrl = (album: Album) => {
    return album.cover_url ?? null;
};

const openPhoto = (photo: Photo) => {
    selectedPhoto.value = photo;
};

const closePhoto = () => {
    selectedPhoto.value = null;
};

const togglePhotoSelection = (photo: Photo) => {
    if (selectedPhotos.value.includes(photo.id)) {
        selectedPhotos.value = selectedPhotos.value.filter(
            (id) => id !== photo.id,
        );

        return;
    }

    selectedPhotos.value.push(photo.id);
};

const isPhotoSelected = (photo: Photo) => {
    return selectedPhotos.value.includes(photo.id);
};

const clearSelection = () => {
    selectedPhotos.value = [];
};

const selectAll = () => {
    if (selectedPhotos.value.length === filteredPhotos.value.length) {
        selectedPhotos.value = [];

        return;
    }

    selectedPhotos.value = filteredPhotos.value.map((photo) => photo.id);
};

const selectAlbum = (albumId: number | null) => {
    activeAlbumId.value = albumId;
    selectedPhotos.value = [];
};

const handleMobileAlbumChange = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    selectAlbum(value ? Number(value) : null);
};

const toggleFilters = () => {
    showFilters.value = !showFilters.value;
};

const resetFilters = () => {
    activeTripId.value = null;
    dateFrom.value = '';
    dateTo.value = '';
};

const hasActiveFilters = computed(() => {
    return (
        activeTripId.value !== null ||
        dateFrom.value !== '' ||
        dateTo.value !== ''
    );
});

const openCreateAlbum = () => {
    newAlbumName.value = '';
    newAlbumDescription.value = '';
    showCreateAlbum.value = true;
};

const closeCreateAlbum = () => {
    if (isCreatingAlbum.value) {
        return;
    }

    showCreateAlbum.value = false;
};

const createAlbum = () => {
    if (!newAlbumName.value.trim()) {
        return;
    }

    isCreatingAlbum.value = true;

    router.post(
        '/albums',
        {
            name: newAlbumName.value.trim(),
            description: newAlbumDescription.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCreateAlbum.value = false;
                newAlbumName.value = '';
                newAlbumDescription.value = '';
            },
            onFinish: () => {
                isCreatingAlbum.value = false;
            },
        },
    );
};

const addPhotosToAlbum = (albumId: number, photoIds: number[]) => {
    if (!photoIds.length || isAddingToAlbum.value) {
        return;
    }

    isAddingToAlbum.value = true;

    router.post(
        `/albums/${albumId}/photos`,
        {
            photo_ids: photoIds,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedPhotos.value = [];
            },
            onFinish: () => {
                isAddingToAlbum.value = false;
            },
        },
    );
};

const addSelectedToAlbum = (albumId: number) => {
    addPhotosToAlbum(albumId, selectedPhotos.value);
};

const removeSelectedFromAlbum = () => {
    if (
        activeAlbumId.value === null ||
        !selectedPhotos.value.length ||
        isRemovingFromAlbum.value
    ) {
        return;
    }

    isRemovingFromAlbum.value = true;

    const albumId = activeAlbumId.value;
    const photoIds = [...selectedPhotos.value];

    const removeNext = (index: number) => {
        if (index >= photoIds.length) {
            selectedPhotos.value = [];
            isRemovingFromAlbum.value = false;
            return;
        }

        router.delete(
            `/albums/${albumId}/photos/${photoIds[index]}`,
            {
                preserveScroll: true,
                onFinish: () => {
                    removeNext(index + 1);
                },
            },
        );
    };

    removeNext(0);
};

const openDeleteConfirmation = () => {
    if (!selectedPhotos.value.length || isDeletingPhotos.value) {
        return;
    }

    photosToDelete.value = [...selectedPhotos.value];
    deleteError.value = '';
    showDeleteConfirmation.value = true;
};

const closeDeleteConfirmation = () => {
    if (isDeletingPhotos.value) {
        return;
    }

    showDeleteConfirmation.value = false;
    photosToDelete.value = [];
    deleteError.value = '';
};

const deleteSelectedPhotos = () => {
    if (!photosToDelete.value.length || isDeletingPhotos.value) {
        return;
    }

    isDeletingPhotos.value = true;
    deleteError.value = '';

    const photoIds = [...photosToDelete.value];
    const viewerPhotoId = selectedPhoto.value?.id ?? null;
    let failed = false;

    const deleteNext = (index: number) => {
        if (failed) {
            isDeletingPhotos.value = false;
            return;
        }

        if (index >= photoIds.length) {
            selectedPhotos.value = [];
            photosToDelete.value = [];
            showDeleteConfirmation.value = false;
            isDeletingPhotos.value = false;

            if (
                viewerPhotoId !== null &&
                photoIds.includes(viewerPhotoId)
            ) {
                selectedPhoto.value = null;
            }

            return;
        }

        router.delete(`/photos/${photoIds[index]}`, {
            preserveScroll: true,

            onError: () => {
                failed = true;
                deleteError.value =
                    'Something went wrong while deleting the photos.';
            },

            onFinish: () => {
                if (failed) {
                    isDeletingPhotos.value = false;
                    return;
                }

                deleteNext(index + 1);
            },
        });
    };

    deleteNext(0);
};

const startDrag = (event: DragEvent, photo: Photo) => {
    if (!event.dataTransfer) {
        return;
    }

    const ids = selectedPhotos.value.includes(photo.id)
        ? [...selectedPhotos.value]
        : [photo.id];

    draggedPhotoId.value = photo.id;
    draggedPhotoIds.value = ids;
    isDragging.value = true;

    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('text/plain', ids.join(','));
};

const enterAlbumDrag = (albumId: number) => {
    dragOverAlbumId.value = albumId;
};

const leaveAlbumDrag = (albumId: number) => {
    if (dragOverAlbumId.value === albumId) {
        dragOverAlbumId.value = null;
    }
};

const dropOnAlbum = (event: DragEvent, albumId: number) => {
    event.preventDefault();

    const ids =
        draggedPhotoIds.value.length > 0
            ? [...draggedPhotoIds.value]
            : draggedPhotoId.value !== null
              ? [draggedPhotoId.value]
              : [];

    dragOverAlbumId.value = null;
    isDragging.value = false;
    draggedPhotoId.value = null;
    draggedPhotoIds.value = [];

    if (!ids.length) {
        return;
    }

    addPhotosToAlbum(albumId, ids);
};

const endDrag = () => {
    dragOverAlbumId.value = null;
    isDragging.value = false;
    draggedPhotoId.value = null;
    draggedPhotoIds.value = [];
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        if (showDeleteConfirmation.value) {
            closeDeleteConfirmation();
            return;
        }

        if (showCreateAlbum.value) {
            closeCreateAlbum();
            return;
        }

        if (selectedPhoto.value) {
            closePhoto();
            return;
        }

        if (selectedPhotos.value.length) {
            clearSelection();
        }
    }
};

window.addEventListener('keydown', handleKeydown);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
});

const formatDate = (date: string | null | undefined) => {
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
    <AppLayout title="Photos">
        <div class="vyamap-container">
            <header class="vyamap-page-header">
                <div>
                    <h1 class="vyamap-page-title">Photos</h1>
                    <p class="vyamap-page-description">
                        <template v-if="photos.length">
                            The photos already added to your trips,
                            <span class="vyamap-number font-semibold text-[var(--vyamap-text)]">{{ photos.length }}</span>
                            in total, private to you. New uploads are turned off.
                        </template>
                        <template v-else>Photos added to your trips would appear here.</template>
                    </p>
                </div>

                <div v-if="photos.length" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <div class="relative sm:w-64">
                        <label for="photo-search" class="sr-only">Search photos</label>
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--vyamap-text-subtle)]">
                            <Icon name="search" :size="18" />
                        </span>
                        <input id="photo-search" v-model="search" type="search" placeholder="Search by file name..." class="vyamap-input !pl-10" />
                    </div>
                    <button
                        type="button"
                        class="vyamap-button-secondary"
                        :aria-expanded="showFilters"
                        aria-controls="photo-filters"
                        @click="toggleFilters"
                    >
                        Filters
                        <span v-if="hasActiveFilters" class="h-2 w-2 rounded-full bg-[var(--vyamap-cyan)]" aria-label="Filters active" />
                    </button>
                </div>
            </header>

            <!-- FILTROS -->
            <section v-if="showFilters" id="photo-filters" class="vyamap-panel mb-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="photo-filter-trip" class="vyamap-label">Trip</label>
                        <select id="photo-filter-trip" v-model="activeTripId" class="vyamap-input">
                            <option :value="null">All trips</option>
                            <option v-for="trip in trips" :key="trip.id" :value="trip.id">{{ trip.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="photo-filter-from" class="vyamap-label">Taken from</label>
                        <input id="photo-filter-from" v-model="dateFrom" type="date" class="vyamap-input" />
                    </div>
                    <div>
                        <label for="photo-filter-to" class="vyamap-label">Taken until</label>
                        <input id="photo-filter-to" v-model="dateTo" type="date" class="vyamap-input" />
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-[var(--vyamap-border)] pt-4 text-sm">
                    <span class="vyamap-number text-[var(--vyamap-text-muted)]">{{ filteredPhotos.length }} photos match your filters</span>
                    <button type="button" class="vyamap-button-ghost vyamap-button-sm" :disabled="!hasActiveFilters" @click="resetFilters">Reset filters</button>
                </div>
            </section>

            <EmptyState
                v-if="!photos.length"
                icon="photo"
                :image="stockImage('snowy-peaks')"
                title="No photos yet"
                text="Photo uploads are turned off in VyaMap. Your trips keep their maps, cities and flights."
            >
                <template #actions>
                    <Link href="/trips" class="vyamap-button-secondary">Go to my trips</Link>
                </template>
            </EmptyState>

            <div v-else class="lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-6">
                <!-- ÁLBUMES (móvil) -->
                <div class="mb-4 lg:hidden">
                    <label for="mobile-album" class="vyamap-label">Album</label>
                    <div class="flex gap-2">
                        <select id="mobile-album" :value="activeAlbumId ?? ''" class="vyamap-input" @change="handleMobileAlbumChange">
                            <option value="">All photos · {{ photos.length }}</option>
                            <option v-for="album in albums" :key="album.id" :value="album.id">
                                {{ album.name }} · {{ album.photos_count ?? 0 }} {{ (album.photos_count ?? 0) === 1 ? 'photo' : 'photos' }}
                            </option>
                        </select>
                        <button type="button" class="vyamap-icon-button !h-11 !w-11" aria-label="New album" @click="openCreateAlbum">
                            <Icon name="plus" :size="18" />
                        </button>
                    </div>
                </div>

                <!-- ÁLBUMES (escritorio) -->
                <aside class="hidden lg:block" aria-label="Albums">
                    <div class="lg:sticky lg:top-6">
                        <div class="mb-2 flex items-center justify-between">
                            <h2 class="text-sm font-semibold">Albums</h2>
                            <button type="button" class="vyamap-button-ghost vyamap-button-sm !px-2" aria-label="New album" title="New album" @click="openCreateAlbum">
                                <Icon name="plus" :size="16" />
                            </button>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <button
                                type="button"
                                class="vyamap-album-item"
                                :class="{ 'is-active': activeAlbumId === null }"
                                :aria-pressed="activeAlbumId === null"
                                @click="selectAlbum(null)"
                            >
                                <span class="vyamap-album-thumb"><Icon name="photo" :size="18" /></span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">All photos</span>
                                    <span class="vyamap-number block text-[0.75rem] text-[var(--vyamap-text-subtle)]">{{ photos.length }} photos</span>
                                </span>
                            </button>

                            <div
                                v-for="album in albums"
                                :key="album.id"
                                @dragenter.prevent="enterAlbumDrag(album.id)"
                                @dragover.prevent="dragOverAlbumId = album.id"
                                @dragleave="leaveAlbumDrag(album.id)"
                                @drop="dropOnAlbum($event, album.id)"
                            >
                                <button
                                    type="button"
                                    class="vyamap-album-item"
                                    :class="{ 'is-active': activeAlbumId === album.id, 'is-drop-target': dragOverAlbumId === album.id }"
                                    :aria-pressed="activeAlbumId === album.id"
                                    @click="selectAlbum(album.id)"
                                >
                                    <span class="vyamap-album-thumb">
                                        <img v-if="albumCoverUrl(album)" :src="albumCoverUrl(album)!" alt="" class="h-full w-full object-cover" />
                                        <Icon v-else name="image-plus" :size="18" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium">{{ album.name }}</span>
                                        <span class="vyamap-number block text-[0.75rem] text-[var(--vyamap-text-subtle)]">
                                            {{ album.photos_count ?? 0 }} {{ (album.photos_count ?? 0) === 1 ? 'photo' : 'photos' }}
                                        </span>
                                    </span>
                                    <span v-if="dragOverAlbumId === album.id" class="text-[0.75rem] font-semibold text-[var(--vyamap-cyan-soft)]">Drop</span>
                                </button>
                            </div>

                            <button type="button" class="vyamap-album-item border-dashed" @click="openCreateAlbum">
                                <span class="vyamap-album-thumb"><Icon name="plus" :size="18" /></span>
                                <span class="text-sm text-[var(--vyamap-text-muted)]">New album</span>
                            </button>
                        </div>

                        <p v-if="albums.length" class="vyamap-help mt-3">
                            {{ isDragging ? 'Drop the photo on an album to add it.' : 'Drag photos onto an album to add them.' }}
                        </p>
                    </div>
                </aside>

                <!-- GALERÍA -->
                <section class="min-w-0" aria-label="Gallery">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">
                            {{ activeAlbum ? activeAlbum.name : 'All photos' }}
                            <span class="vyamap-number ml-1 font-normal text-[var(--vyamap-text-muted)]">{{ filteredPhotos.length }}</span>
                        </h2>
                        <button v-if="filteredPhotos.length" type="button" class="vyamap-button-ghost vyamap-button-sm" @click="selectAll">
                            {{ selectedPhotos.length === filteredPhotos.length ? 'Clear selection' : 'Select all' }}
                        </button>
                    </div>

                    <!-- BARRA DE SELECCIÓN -->
                    <div
                        v-if="selectedPhotos.length"
                        class="vyamap-glass sticky top-[calc(3.75rem+env(safe-area-inset-top))] z-30 mb-4 flex flex-col gap-3 rounded-[var(--vyamap-radius-lg)] px-4 py-3 sm:flex-row sm:items-center sm:justify-between lg:top-4"
                        role="region"
                        aria-label="Selection"
                    >
                        <div class="flex items-center gap-2 text-sm">
                            <button type="button" class="vyamap-button-ghost vyamap-button-sm !px-2" aria-label="Clear selection" @click="clearSelection">
                                <Icon name="close" :size="16" />
                            </button>
                            <span class="vyamap-number font-medium">{{ selectedPhotos.length }} {{ selectedPhotos.length === 1 ? 'photo selected' : 'photos selected' }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <template v-if="albums.length">
                                <span class="text-[0.8125rem] text-[var(--vyamap-text-muted)]">Add to</span>
                                <button
                                    v-for="album in albums"
                                    :key="album.id"
                                    type="button"
                                    class="vyamap-button-secondary vyamap-button-sm"
                                    :disabled="isAddingToAlbum"
                                    @click="addSelectedToAlbum(album.id)"
                                >
                                    {{ album.name }}
                                </button>
                            </template>
                            <button
                                v-if="activeAlbum"
                                type="button"
                                class="vyamap-button-secondary vyamap-button-sm"
                                :disabled="isRemovingFromAlbum"
                                @click="removeSelectedFromAlbum"
                            >
                                Remove from album
                            </button>
                            <button
                                type="button"
                                class="vyamap-button-danger vyamap-button-sm"
                                :disabled="isDeletingPhotos || isRemovingFromAlbum || isAddingToAlbum"
                                @click="openDeleteConfirmation"
                            >
                                <Icon name="trash" :size="16" />
                                Delete
                            </button>
                        </div>
                    </div>

                    <ul v-if="filteredPhotos.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 xl:grid-cols-4">
                        <li
                            v-for="photo in filteredPhotos"
                            :key="photo.id"
                            draggable="true"
                            class="group relative aspect-[4/5] overflow-hidden rounded-[var(--vyamap-radius-lg)] border bg-[var(--vyamap-surface-strong)] transition-colors"
                            :class="isPhotoSelected(photo) ? 'border-[var(--vyamap-cyan)] ring-2 ring-[rgba(34,211,238,0.35)]' : 'border-[var(--vyamap-border)] hover:border-[var(--vyamap-border-strong)]'"
                            @dragstart="startDrag($event, photo)"
                            @dragend="endDrag"
                        >
                            <button
                                type="button"
                                class="absolute inset-0 h-full w-full cursor-grab active:cursor-grabbing"
                                :aria-label="`Open ${photo.original_filename ?? 'photo'}`"
                                @click="openPhoto(photo)"
                            >
                                <img :src="photoUrl(photo)" :alt="photo.original_filename ?? 'Travel photo'" class="h-full w-full object-cover" loading="lazy" />
                            </button>

                            <button
                                type="button"
                                class="absolute left-2.5 top-2.5 flex h-8 w-8 items-center justify-center rounded-full border transition-opacity focus-visible:opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
                                :class="isPhotoSelected(photo)
                                    ? '!opacity-100 border-[var(--vyamap-cyan)] bg-[var(--vyamap-cyan)] text-[#041019]'
                                    : 'border-white/40 bg-[rgba(7,11,20,0.55)] text-transparent'"
                                :aria-pressed="isPhotoSelected(photo)"
                                :aria-label="`Select ${photo.original_filename ?? 'photo'}`"
                                @click.stop="togglePhotoSelection(photo)"
                            >
                                <Icon name="check" :size="16" />
                            </button>

                            <span
                                v-if="photo.taken_at"
                                class="vyamap-number pointer-events-none absolute bottom-2.5 left-2.5 rounded-[var(--vyamap-radius-sm)] bg-[rgba(7,11,20,0.7)] px-2 py-1 text-[0.75rem] text-[var(--vyamap-text)] sm:opacity-0 sm:group-hover:opacity-100"
                            >
                                {{ formatDate(photo.taken_at) }}
                            </span>
                        </li>
                    </ul>

                    <EmptyState v-else-if="search" icon="search" title="No photos found" text="Try searching with another filename.">
                        <template #actions>
                            <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="search = ''">Clear search</button>
                        </template>
                    </EmptyState>

                    <EmptyState
                        v-else
                        icon="photo"
                        title="No photos here"
                        text="There are no photos matching the current selection."
                    >
                        <template #actions>
                            <button v-if="hasActiveFilters" type="button" class="vyamap-button-secondary vyamap-button-sm" @click="resetFilters">Reset filters</button>
                            <button v-if="activeAlbumId !== null" type="button" class="vyamap-button-secondary vyamap-button-sm" @click="selectAlbum(null)">Show all photos</button>
                        </template>
                    </EmptyState>
                </section>
            </div>
        </div>

        <!-- CREAR ÁLBUM -->
        <Teleport to="body">
            <div
                v-if="showCreateAlbum"
                class="fixed inset-0 z-[110] flex items-end justify-center bg-[rgba(4,7,14,0.7)] p-4 sm:items-center"
                @click.self="closeCreateAlbum"
            >
                <form
                    class="vyamap-glass w-full max-w-md rounded-[var(--vyamap-radius-xl)] p-5 sm:p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="create-album-title"
                    @submit.prevent="createAlbum"
                >
                    <h2 id="create-album-title" class="vyamap-h2">Create an album</h2>
                    <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Group your travel memories into a collection.</p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="album-name" class="vyamap-label">Name</label>
                            <input id="album-name" v-model="newAlbumName" type="text" maxlength="100" placeholder="e.g. Albania 2026" class="vyamap-input" />
                        </div>
                        <div>
                            <label for="album-description" class="vyamap-label">Description <span class="text-[var(--vyamap-text-subtle)]">(optional)</span></label>
                            <textarea id="album-description" v-model="newAlbumDescription" rows="3" maxlength="1000" class="vyamap-textarea"></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="vyamap-button-ghost" :disabled="isCreatingAlbum" @click="closeCreateAlbum">Cancel</button>
                        <button type="submit" class="vyamap-button" :disabled="!newAlbumName.trim() || isCreatingAlbum">
                            {{ isCreatingAlbum ? 'Creating...' : 'Create album' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- VISOR -->
        <Teleport to="body">
            <div
                v-if="selectedPhoto"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-[rgba(4,7,14,0.94)] p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="selectedPhoto.original_filename ?? 'Photo'"
                @click.self="closePhoto"
            >
                <button type="button" class="vyamap-icon-button absolute right-4 top-4" aria-label="Close photo" @click="closePhoto">
                    <Icon name="close" :size="20" />
                </button>
                <figure class="flex max-h-[92vh] max-w-[94vw] flex-col items-center gap-3">
                    <img
                        :src="fullPhotoUrl(selectedPhoto)"
                        :alt="selectedPhoto.original_filename ?? 'Travel photo'"
                        class="max-h-[84vh] max-w-[94vw] rounded-[var(--vyamap-radius-lg)] object-contain"
                    />
                    <figcaption class="text-sm text-[var(--vyamap-text-muted)]">
                        {{ selectedPhoto.original_filename }}
                        <template v-if="formatDate(selectedPhoto.taken_at)"> · <span class="vyamap-number">{{ formatDate(selectedPhoto.taken_at) }}</span></template>
                    </figcaption>
                </figure>
            </div>
        </Teleport>

        <!-- CONFIRMAR BORRADO -->
        <Teleport to="body">
            <div
                v-if="showDeleteConfirmation"
                class="fixed inset-0 z-[120] flex items-end justify-center bg-[rgba(4,7,14,0.75)] p-4 sm:items-center"
                @click.self="closeDeleteConfirmation"
            >
                <div
                    class="vyamap-glass w-full max-w-md rounded-[var(--vyamap-radius-xl)] p-5 sm:p-6"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="delete-photos-title"
                    aria-describedby="delete-photos-text"
                >
                    <span class="flex h-11 w-11 items-center justify-center rounded-[var(--vyamap-radius-md)] border border-[rgba(251,113,133,0.35)] bg-[rgba(251,113,133,0.1)] text-[var(--vyamap-danger)]">
                        <Icon name="trash" :size="20" />
                    </span>
                    <h2 id="delete-photos-title" class="vyamap-h2 mt-4">
                        Delete {{ photosToDelete.length }} {{ photosToDelete.length === 1 ? 'photo' : 'photos' }}?
                    </h2>
                    <p id="delete-photos-text" class="mt-2 text-sm text-[var(--vyamap-text-muted)]">
                        These photos will be permanently deleted. This action cannot be undone.
                    </p>
                    <p v-if="deleteError" role="alert" class="vyamap-error">{{ deleteError }}</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="vyamap-button-ghost" :disabled="isDeletingPhotos" @click="closeDeleteConfirmation">Cancel</button>
                        <button type="button" class="vyamap-button-danger" :disabled="isDeletingPhotos" @click="deleteSelectedPhotos">
                            {{ isDeletingPhotos ? 'Deleting...' : 'Delete photos' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.vyamap-album-item {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid var(--vyamap-border);
    border-radius: var(--vyamap-radius-md);
    background: var(--vyamap-surface-muted);
    padding: 0.5rem;
    text-align: left;
    transition: border-color 0.15s var(--vyamap-ease), background-color 0.15s var(--vyamap-ease);
}

.vyamap-album-item:hover {
    border-color: var(--vyamap-border-strong);
    background: var(--vyamap-surface-strong);
}

.vyamap-album-item.is-active {
    border-color: rgba(34, 211, 238, 0.4);
    background: rgba(34, 211, 238, 0.08);
}

.vyamap-album-item.is-drop-target {
    border-color: var(--vyamap-cyan);
    background: rgba(34, 211, 238, 0.14);
}

.vyamap-album-thumb {
    display: flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: var(--vyamap-radius-sm);
    background: var(--vyamap-surface-raised);
    color: var(--vyamap-text-muted);
}
</style>
