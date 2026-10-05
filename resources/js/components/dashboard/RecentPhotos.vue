<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Photo {
    id: number;
    path: string;
    trip: {
        id: number;
        name: string;
    } | null;
}

defineProps<{
    photos: Photo[];
}>();
</script>

<template>
    <section>
        <div class="flex items-end justify-between gap-6">
            <div>
                <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                    Memories
                </p>

                <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                    Recent photos
                </h2>
            </div>
        </div>

        <div
            v-if="photos.length"
            class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4"
        >
            <Link
                v-for="photo in photos"
                :key="photo.id"
                :href="photo.trip ? `/trips/${photo.trip.id}` : '#'"
                class="group relative aspect-square overflow-hidden rounded-[var(--vyamap-radius-md)] bg-[var(--vyamap-surface-muted)]"
            >
                <img
                    :src="photo.path"
                    alt=""
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                />

                <div
                    v-if="photo.trip"
                    class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent px-3 pb-3 pt-8"
                >
                    <p class="truncate text-xs font-medium text-white">
                        {{ photo.trip.name }}
                    </p>
                </div>
            </Link>
        </div>

        <div
            v-else
            class="mt-5 rounded-[var(--vyamap-radius-md)] border border-dashed border-[var(--vyamap-border)] px-6 py-10 text-center"
        >
            <p class="text-sm text-[var(--vyamap-text-muted)]">
                No photos yet.
            </p>

            <p class="mt-1 text-xs text-[var(--vyamap-text-muted)]">
                Your travel memories will appear here.
            </p>
        </div>
    </section>
</template>