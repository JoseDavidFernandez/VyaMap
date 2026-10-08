<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';

interface Photo {
    id: number;
    trip_id?: number | null;
    visit_id?: number | null;
    place_id?: number | null;
    journal_entry_id?: number | null;
    path: string;
    thumbnail_path?: string | null;
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
    cover_photo?: {
        id: number;
        path: string;
        thumbnail_path?: string | null;
    } | null;
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
    return `/storage/${photo.thumbnail_path ?? photo.path}`;
};

const fullPhotoUrl = (photo: Photo) => {
    return `/storage/${photo.path}`;
};

const albumCoverUrl = (album: Album) => {
    if (!album.cover_photo) {
        return null;
    }

    return `/storage/${album.cover_photo.thumbnail_path ?? album.cover_photo.path}`;
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
        <div class="vyamap-page">
            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >
                <!-- HEADER -->
                <section class="mb-8">
                    <div
                        class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-5 py-7 sm:px-10 sm:py-11 lg:px-12 lg:py-12"
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
                                Memories
                            </div>

                            <div
                                class="mt-3 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                            >
                                <div>
                                    <h1
                                        class="text-3xl font-semibold leading-[0.95] tracking-[-0.065em] text-white sm:text-5xl lg:text-6xl"
                                    >
                                        Your travel
                                        <br class="hidden sm:block" />
                                        memories.
                                    </h1>

                                    <p
                                        class="mt-4 max-w-xl text-sm leading-6 text-white/40 sm:mt-5 sm:text-base"
                                    >
                                        Every place you've visited, captured
                                        in one place.
                                    </p>
                                </div>

                                <div
                                    class="shrink-0 text-[10px] uppercase tracking-[0.18em] text-white/25"
                                >
                                    {{ photos.length }}
                                    {{
                                        photos.length === 1 ? 'photo' : 'photos'
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- TOOLBAR -->
                <section class="mb-6">
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="text-[9px] uppercase tracking-[0.24em] text-white/25"
                            >
                                Gallery
                            </div>

                            <div
                                class="h-3 w-px bg-white/[0.08]"
                            ></div>

                            <div class="text-xs text-white/30">
                                {{ filteredPhotos.length }}
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <!-- SEARCH -->
                            <div class="relative sm:w-[250px]">
                                <input
                                    v-model="search"
                                    type="search"
                                    placeholder="Search photos..."
                                    class="w-full rounded-full border border-white/[0.08] bg-white/[0.035] px-4 py-2.5 pr-10 text-xs text-white outline-none transition placeholder:text-white/20 focus:border-white/[0.16] focus:bg-white/[0.05]"
                                />

                                <div
                                    class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm text-white/25"
                                >
                                    ⌕
                                </div>
                            </div>

                            <!-- FILTERS -->
                            <button
                                type="button"
                                class="rounded-full border border-white/[0.08] bg-white/[0.035] px-4 py-2.5 text-[11px] text-white/45 transition hover:border-white/[0.14] hover:bg-white/[0.05] hover:text-white/70"
                                :class="{
                                    'border-white/[0.16] bg-white/[0.07] text-white':
                                        showFilters,
                                }"
                                @click="toggleFilters"
                            >
                                Filters
                                <span
                                    v-if="hasActiveFilters"
                                    class="ml-1 text-cyan-300"
                                >
                                    ·
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- FILTER PANEL -->
                    <div
                        v-if="showFilters"
                        class="mt-4 rounded-[22px] border border-white/[0.08] bg-white/[0.025] p-5"
                    >
                        <div
                            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <!-- TRIP -->
                            <label class="block">
                                <span
                                    class="text-[9px] uppercase tracking-[0.20em] text-white/25"
                                >
                                    Trip
                                </span>

                                <select
                                    v-model="activeTripId"
                                    class="mt-2 w-full rounded-xl border border-white/[0.08] bg-[#151923] px-3 py-2.5 text-xs text-white/60 outline-none transition focus:border-white/[0.16]"
                                >
                                    <option :value="null">
                                        All trips
                                    </option>

                                    <option
                                        v-for="trip in trips"
                                        :key="trip.id"
                                        :value="trip.id"
                                    >
                                        {{ trip.name }}
                                    </option>
                                </select>
                            </label>

                            <!-- FROM -->
                            <label class="block">
                                <span
                                    class="text-[9px] uppercase tracking-[0.20em] text-white/25"
                                >
                                    From
                                </span>

                                <input
                                    v-model="dateFrom"
                                    type="date"
                                    class="mt-2 w-full rounded-xl border border-white/[0.08] bg-[#151923] px-3 py-2.5 text-xs text-white/60 outline-none transition focus:border-white/[0.16]"
                                />
                            </label>

                            <!-- TO -->
                            <label class="block">
                                <span
                                    class="text-[9px] uppercase tracking-[0.20em] text-white/25"
                                >
                                    To
                                </span>

                                <input
                                    v-model="dateTo"
                                    type="date"
                                    class="mt-2 w-full rounded-xl border border-white/[0.08] bg-[#151923] px-3 py-2.5 text-xs text-white/60 outline-none transition focus:border-white/[0.16]"
                                />
                            </label>
                        </div>

                        <div
                            class="mt-4 flex items-center justify-between border-t border-white/[0.06] pt-4"
                        >
                            <div class="text-[10px] text-white/20">
                                {{ filteredPhotos.length }} photos match your
                                filters
                            </div>

                            <button
                                type="button"
                                class="text-[10px] text-white/30 transition hover:text-white/60"
                                @click="resetFilters"
                            >
                                Reset filters
                            </button>
                        </div>
                    </div>
                </section>

                <!-- MAIN CONTENT -->
                <div class="lg:grid lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-7">
                    <!-- MOBILE ALBUM FILTER -->
                    <div class="mb-5 lg:hidden">
                        <label
                            for="mobile-album"
                            class="mb-2 block text-[9px] uppercase tracking-[0.22em] text-white/25"
                        >
                            Album
                        </label>

                        <select
                            id="mobile-album"
                            :value="activeAlbumId ?? ''"
                            class="w-full appearance-none rounded-[16px] border border-white/[0.08] bg-[#151923] px-4 py-3 text-xs text-white/60 outline-none transition focus:border-white/[0.16]"
                            @change="handleMobileAlbumChange"
                        >
                            <option value="">
                                All photos · {{ photos.length }}
                            </option>

                            <option
                                v-for="album in albums"
                                :key="album.id"
                                :value="album.id"
                            >
                                {{ album.name }} · {{ album.photos_count ?? 0 }}
                                {{ (album.photos_count ?? 0) === 1 ? 'photo' : 'photos' }}
                            </option>
                        </select>
                    </div>

                    <!-- ALBUMS SIDEBAR -->
                    <aside class="mb-6 hidden lg:mb-0 lg:block">
                        <div
                            class="lg:sticky lg:top-6"
                        >
                            <div
                                class="mb-3 flex items-center justify-between"
                            >
                                <div
                                    class="text-[9px] uppercase tracking-[0.22em] text-white/25"
                                >
                                    Albums
                                </div>

                                <button
                                    type="button"
                                    class="text-[10px] text-white/25 transition hover:text-white/70"
                                    @click="openCreateAlbum"
                                >
                                    +
                                </button>
                            </div>

                            <div
                                class="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-2 lg:overflow-visible"
                            >
                                <!-- ALL PHOTOS -->
                                <button
                                    type="button"
                                    class="group flex min-w-[170px] shrink-0 items-center gap-3 rounded-[16px] border p-2 text-left transition lg:w-full"
                                    :class="
                                        activeAlbumId === null
                                            ? 'border-white/[0.12] bg-white/[0.07]'
                                            : 'border-white/[0.06] bg-white/[0.02] hover:border-white/[0.10] hover:bg-white/[0.045]'
                                    "
                                    @click="selectAlbum(null)"
                                >
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-[11px] border border-white/[0.07] bg-white/[0.04]"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            class="h-5 w-5 text-white/30"
                                        >
                                            <path
                                                d="M4 5.5A1.5 1.5 0 0 1 5.5 4h5A1.5 1.5 0 0 1 12 5.5v5a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 4 10.5v-5ZM12 5.5A1.5 1.5 0 0 1 13.5 4h5A1.5 1.5 0 0 1 20 5.5v5a1.5 1.5 0 0 1-1.5 1.5h-5a1.5 1.5 0 0 1-1.5-1.5v-5ZM4 13.5A1.5 1.5 0 0 1 5.5 12h5a1.5 1.5 0 0 1 1.5 1.5v5a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 4 18.5v-5ZM12 13.5a1.5 1.5 0 0 1 1.5-1.5h5a1.5 1.5 0 0 1 1.5 1.5v5a1.5 1.5 0 0 1-1.5 1.5h-5a1.5 1.5 0 0 1-1.5-1.5v-5Z"
                                                stroke="currentColor"
                                                stroke-width="1.4"
                                            />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <div
                                            class="truncate text-[11px]"
                                            :class="
                                                activeAlbumId === null
                                                    ? 'text-white'
                                                    : 'text-white/50 group-hover:text-white/70'
                                            "
                                        >
                                            All photos
                                        </div>

                                        <div
                                            class="mt-0.5 text-[9px] text-white/20"
                                        >
                                            {{ photos.length }} photos
                                        </div>
                                    </div>
                                </button>

                                <!-- ALBUMS -->
                                <div
                                    v-for="album in albums"
                                    :key="album.id"
                                    class="relative"
                                    @dragenter.prevent="enterAlbumDrag(album.id)"
                                    @dragover.prevent="
                                        dragOverAlbumId = album.id
                                    "
                                    @dragleave="leaveAlbumDrag(album.id)"
                                    @drop="dropOnAlbum($event, album.id)"
                                >
                                    <button
                                        type="button"
                                        class="group flex min-w-[170px] shrink-0 items-center gap-3 rounded-[16px] border p-2 text-left transition lg:w-full"
                                        :class="
                                            dragOverAlbumId === album.id
                                                ? 'scale-[1.02] border-cyan-300/40 bg-cyan-300/[0.10] shadow-[0_0_30px_rgba(103,232,249,0.08)]'
                                                : activeAlbumId === album.id
                                                  ? 'border-white/[0.12] bg-white/[0.07]'
                                                  : 'border-white/[0.06] bg-white/[0.02] hover:border-white/[0.10] hover:bg-white/[0.045]'
                                        "
                                        @click="selectAlbum(album.id)"
                                    >
                                        <div
                                            class="relative h-11 w-11 shrink-0 overflow-hidden rounded-[11px] border border-white/[0.07] bg-white/[0.04]"
                                        >
                                            <img
                                                v-if="albumCoverUrl(album)"
                                                :src="albumCoverUrl(album)!"
                                                alt=""
                                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                            />

                                            <div
                                                v-else
                                                class="flex h-full w-full items-center justify-center text-white/20"
                                            >
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    class="h-5 w-5"
                                                >
                                                    <path
                                                        d="M3.5 7.5A2.5 2.5 0 0 1 6 5h4l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.4"
                                                    />
                                                </svg>
                                            </div>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="truncate text-[11px]"
                                                :class="
                                                    activeAlbumId === album.id
                                                        ? 'text-white'
                                                        : 'text-white/50 group-hover:text-white/70'
                                                "
                                            >
                                                {{ album.name }}
                                            </div>

                                            <div
                                                class="mt-0.5 text-[9px] text-white/20"
                                            >
                                                {{
                                                    album.photos_count ?? 0
                                                }}
                                                {{
                                                    (album.photos_count ?? 0) ===
                                                    1
                                                        ? 'photo'
                                                        : 'photos'
                                                }}
                                            </div>
                                        </div>

                                        <div
                                            v-if="dragOverAlbumId === album.id"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[9px] font-medium text-cyan-200"
                                        >
                                            Drop
                                        </div>
                                    </button>
                                </div>

                                <!-- NEW ALBUM -->
                                <button
                                    type="button"
                                    class="flex min-w-[170px] shrink-0 items-center gap-3 rounded-[16px] border border-dashed border-white/[0.07] p-2 text-left transition hover:border-white/[0.13] hover:bg-white/[0.025] lg:w-full"
                                    @click="openCreateAlbum"
                                >
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] border border-white/[0.07] bg-white/[0.02] text-lg text-white/25"
                                    >
                                        +
                                    </div>

                                    <div>
                                        <div
                                            class="text-[11px] text-white/35"
                                        >
                                            New album
                                        </div>

                                        <div
                                            class="mt-0.5 text-[9px] text-white/15"
                                        >
                                            Create a collection
                                        </div>
                                    </div>
                                </button>
                            </div>

                            <div
                                v-if="albums.length && !isDragging"
                                class="mt-4 hidden text-[9px] leading-4 text-white/15 lg:block"
                            >
                                Drag photos onto an album to add them.
                            </div>

                            <div
                                v-if="isDragging"
                                class="mt-4 hidden rounded-[14px] border border-cyan-300/[0.12] bg-cyan-300/[0.04] px-3 py-2.5 text-[9px] leading-4 text-cyan-100/50 lg:block"
                            >
                                Drop the photo here to add it to an album.
                            </div>
                        </div>
                    </aside>

                    <!-- GALLERY AREA -->
                    <section class="min-w-0">
                        <!-- ACTIVE ALBUM HEADER -->
                        <div
                            class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div
                                    class="text-[9px] uppercase tracking-[0.20em] text-white/20"
                                >
                                    {{
                                        activeAlbum
                                            ? activeAlbum.name
                                            : 'All photos'
                                    }}
                                </div>

                                <div
                                    v-if="activeAlbum"
                                    class="mt-1 text-[10px] text-white/20"
                                >
                                    {{
                                        activeAlbum.photos_count ?? 0
                                    }}
                                    {{
                                        (activeAlbum.photos_count ?? 0) === 1
                                            ? 'photo'
                                            : 'photos'
                                    }}
                                </div>
                            </div>

                            <div
                                v-if="filteredPhotos.length"
                                class="flex items-center gap-3"
                            >
                                <div class="text-[10px] text-white/20">
                                    {{ filteredPhotos.length }}
                                </div>

                                <button
                                    type="button"
                                    class="text-[10px] text-white/25 transition hover:text-white/60"
                                    @click="selectAll"
                                >
                                    {{
                                        selectedPhotos.length ===
                                        filteredPhotos.length
                                            ? 'Clear selection'
                                            : 'Select all'
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- SELECTION BAR -->
                        <div
                            v-if="selectedPhotos.length"
                            class="mb-5 flex flex-col gap-3 rounded-[20px] border border-white/[0.08] bg-white/[0.035] px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div
                                class="flex items-center gap-3 text-[11px] text-white/50"
                            >
                                <button
                                    type="button"
                                    class="text-white/70 transition hover:text-white"
                                    @click="clearSelection"
                                >
                                    ×
                                </button>

                                <span>
                                    {{ selectedPhotos.length }}
                                    {{
                                        selectedPhotos.length === 1
                                            ? 'photo selected'
                                            : 'photos selected'
                                    }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Add to album -->
                                <div
                                    v-if="albums.length"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="hidden text-[9px] uppercase tracking-[0.16em] text-white/20 sm:inline"
                                    >
                                        Add to
                                    </span>

                                    <button
                                        v-for="album in albums"
                                        :key="album.id"
                                        type="button"
                                        class="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1.5 text-[10px] text-white/40 transition hover:border-white/[0.14] hover:bg-white/[0.07] hover:text-white/70"
                                        :disabled="isAddingToAlbum"
                                        @click="addSelectedToAlbum(album.id)"
                                    >
                                        {{ album.name }}
                                    </button>
                                </div>

                                <!-- Remove from album -->
                                <button
                                    v-if="activeAlbum"
                                    type="button"
                                    class="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1.5 text-[10px] text-white/40 transition hover:border-white/[0.14] hover:bg-white/[0.07] hover:text-white/70 disabled:opacity-40"
                                    :disabled="isRemovingFromAlbum"
                                    @click="removeSelectedFromAlbum"
                                >
                                    Remove from album
                                </button>

                                <!-- Delete -->
                                <button
                                    type="button"
                                    class="rounded-full border border-red-400/[0.12] bg-red-400/[0.035] px-3 py-1.5 text-[10px] text-red-200/50 transition hover:border-red-300/[0.22] hover:bg-red-400/[0.07] hover:text-red-100 disabled:opacity-40"
                                    :disabled="
                                        isDeletingPhotos ||
                                        isRemovingFromAlbum ||
                                        isAddingToAlbum
                                    "
                                    @click="openDeleteConfirmation"
                                >
                                    Delete
                                </button>

                            </div>
                        </div>

                        <!-- GALLERY -->
                        <div
                            v-if="filteredPhotos.length"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4"
                        >
                            <div
                                v-for="photo in filteredPhotos"
                                :key="photo.id"
                                draggable="true"
                                class="group relative aspect-[4/5] overflow-hidden rounded-[18px] border bg-white/[0.035] shadow-2xl transition duration-300 hover:-translate-y-1 sm:rounded-[22px]"
                                :class="
                                    isPhotoSelected(photo)
                                        ? 'border-white/[0.35] ring-1 ring-white/20'
                                        : 'border-white/[0.08] hover:border-white/[0.16]'
                                "
                                @dragstart="startDrag($event, photo)"
                                @dragend="endDrag"
                            >
                                <button
                                    type="button"
                                    class="absolute inset-0 z-10 h-full w-full cursor-grab active:cursor-grabbing"
                                    @click="openPhoto(photo)"
                                >
                                    <img
                                        :src="photoUrl(photo)"
                                        alt="Travel photo"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                        loading="lazy"
                                    />
                                </button>

                                <!-- SELECT -->
                                <button
                                    type="button"
                                    class="absolute left-3 top-3 z-20 flex h-7 w-7 items-center justify-center rounded-full border border-white/20 bg-black/30 text-[11px] text-white/70 opacity-0 backdrop-blur-md transition group-hover:opacity-100"
                                    :class="
                                        isPhotoSelected(photo)
                                            ? 'border-white/40 bg-white text-black opacity-100'
                                            : ''
                                    "
                                    @click.stop="togglePhotoSelection(photo)"
                                >
                                    <span v-if="isPhotoSelected(photo)">
                                        ✓
                                    </span>
                                </button>

                                <!-- SELECTED INDICATOR -->
                                <div
                                    v-if="isPhotoSelected(photo)"
                                    class="pointer-events-none absolute inset-0 z-[5] border-2 border-white/40 rounded-[22px]"
                                ></div>

                                <!-- DRAG INDICATOR -->
                                <div
                                    class="pointer-events-none absolute right-3 top-3 z-20 flex h-7 w-7 items-center justify-center rounded-full border border-white/10 bg-black/30 text-[11px] text-white/50 opacity-0 backdrop-blur-md transition group-hover:opacity-100"
                                >
                                    ↕
                                </div>

                                <!-- HOVER INFO -->
                                <div
                                    class="pointer-events-none absolute inset-x-0 bottom-0 z-20 flex items-end justify-between bg-gradient-to-t from-black/70 via-black/20 to-transparent px-3 pb-3 pt-14 opacity-0 transition duration-300 group-hover:opacity-100"
                                >
                                    <div
                                        v-if="photo.taken_at"
                                        class="text-[10px] text-white/55"
                                    >
                                        {{ formatDate(photo.taken_at) }}
                                    </div>

                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-full border border-white/10 bg-black/30 text-xs text-white/60 backdrop-blur-md"
                                    >
                                        ↗
                                    </div>
                                </div>
                            </div>
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
                                No photos found
                            </h3>

                            <p
                                class="mx-auto mt-2 max-w-sm text-xs leading-5 text-white/30"
                            >
                                Try searching with another filename.
                            </p>
                        </div>

                        <!-- FILTER EMPTY -->
                        <div
                            v-else-if="
                                activeAlbumId !== null ||
                                activeTripId !== null ||
                                dateFrom ||
                                dateTo
                            "
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
                                No photos here
                            </h3>

                            <p
                                class="mx-auto mt-2 max-w-sm text-xs leading-5 text-white/30"
                            >
                                There are no photos matching the current
                                selection.
                            </p>
                        </div>

                        <!-- NO PHOTOS -->
                        <div
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
                                Your travel memories will appear here once you
                                start adding photos to your trips.
                            </p>
                        </div>
                    </section>
                </div>
            </main>

            <!-- CREATE ALBUM -->
            <Teleport to="body">
                <div
                    v-if="showCreateAlbum"
                    class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-5 backdrop-blur-md"
                    @click.self="closeCreateAlbum"
                >
                    <div
                        class="w-full max-w-[440px] rounded-[26px] border border-white/[0.10] bg-[#151923] p-6 shadow-2xl"
                    >
                        <div
                            class="text-[9px] uppercase tracking-[0.22em] text-white/25"
                        >
                            New album
                        </div>

                        <h2
                            class="mt-2 text-2xl font-semibold tracking-[-0.04em] text-white"
                        >
                            Create an album
                        </h2>

                        <p
                            class="mt-2 text-xs leading-5 text-white/30"
                        >
                            Group your travel memories into a collection.
                        </p>

                        <div class="mt-6 space-y-4">
                            <label class="block">
                                <span
                                    class="text-[9px] uppercase tracking-[0.18em] text-white/25"
                                >
                                    Name
                                </span>

                                <input
                                    v-model="newAlbumName"
                                    type="text"
                                    maxlength="100"
                                    placeholder="e.g. Albania 2026"
                                    class="mt-2 w-full rounded-xl border border-white/[0.08] bg-white/[0.035] px-4 py-3 text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/[0.16]"
                                    @keydown.enter="createAlbum"
                                />
                            </label>

                            <label class="block">
                                <span
                                    class="text-[9px] uppercase tracking-[0.18em] text-white/25"
                                >
                                    Description
                                </span>

                                <textarea
                                    v-model="newAlbumDescription"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="Optional"
                                    class="mt-2 w-full resize-none rounded-xl border border-white/[0.08] bg-white/[0.035] px-4 py-3 text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/[0.16]"
                                ></textarea>
                            </label>
                        </div>

                        <div
                            class="mt-6 flex items-center justify-end gap-2"
                        >
                            <button
                                type="button"
                                class="rounded-full px-4 py-2.5 text-[11px] text-white/35 transition hover:text-white/70"
                                :disabled="isCreatingAlbum"
                                @click="closeCreateAlbum"
                            >
                                Cancel
                            </button>

                            <button
                                type="button"
                                class="rounded-full border border-white/[0.12] bg-white/[0.08] px-5 py-2.5 text-[11px] text-white transition hover:bg-white/[0.12] disabled:opacity-40"
                                :disabled="
                                    !newAlbumName.trim() ||
                                    isCreatingAlbum
                                "
                                @click="createAlbum"
                            >
                                {{
                                    isCreatingAlbum
                                        ? 'Creating...'
                                        : 'Create album'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

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

            <!-- DELETE PHOTOS CONFIRMATION -->
            <Teleport to="body">
                <div
                    v-if="showDeleteConfirmation"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/75 p-5 backdrop-blur-md"
                    @click.self="closeDeleteConfirmation"
                >
                    <div
                        class="w-full max-w-[420px] rounded-[26px] border border-white/[0.10] bg-[#151923] p-6 shadow-2xl"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-[14px] border border-red-400/[0.12] bg-red-400/[0.06] text-red-200/60"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                class="h-5 w-5"
                            >
                                <path
                                    d="M4 7h16M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m-8 0 .7 12.1A2 2 0 0 0 9.7 21h4.6a2 2 0 0 0 2-1.9L17 7M10 11v6M14 11v6"
                                    stroke="currentColor"
                                    stroke-width="1.4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>

                        <div class="mt-5">
                            <div
                                class="text-[9px] uppercase tracking-[0.22em] text-white/25"
                            >
                                Delete photos
                            </div>

                            <h2
                                class="mt-2 text-2xl font-semibold tracking-[-0.04em] text-white"
                            >
                                Delete
                                {{ photosToDelete.length }}
                                {{
                                    photosToDelete.length === 1
                                        ? 'photo'
                                        : 'photos'
                                }}?
                            </h2>

                            <p
                                class="mt-3 text-xs leading-5 text-white/35"
                            >
                                These photos will be permanently deleted. This
                                action cannot be undone.
                            </p>
                        </div>

                        <div
                            v-if="deleteError"
                            class="mt-4 rounded-[14px] border border-red-400/[0.12] bg-red-400/[0.05] px-3 py-2.5 text-[10px] leading-4 text-red-200/60"
                        >
                            {{ deleteError }}
                        </div>

                        <div
                            class="mt-6 flex items-center justify-end gap-2"
                        >
                            <button
                                type="button"
                                class="rounded-full px-4 py-2.5 text-[11px] text-white/35 transition hover:text-white/70 disabled:opacity-30"
                                :disabled="isDeletingPhotos"
                                @click="closeDeleteConfirmation"
                            >
                                Cancel
                            </button>

                            <button
                                type="button"
                                class="rounded-full border border-red-300/[0.16] bg-red-400/[0.08] px-5 py-2.5 text-[11px] text-red-100/80 transition hover:border-red-300/[0.25] hover:bg-red-400/[0.12] hover:text-red-100 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="isDeletingPhotos"
                                @click="deleteSelectedPhotos"
                            >
                                {{
                                    isDeletingPhotos
                                        ? 'Deleting...'
                                        : 'Delete photos'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

        </div>
    </AppLayout>
</template>