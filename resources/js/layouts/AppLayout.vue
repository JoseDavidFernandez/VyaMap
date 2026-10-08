<script setup lang="ts">
import Navbar from '../components/Navbar.vue';

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div
        class="min-h-screen bg-[var(--vyamap-background)] text-[var(--vyamap-text)]"
    >
        <Navbar />

        <main class="min-h-screen pt-16 lg:pl-[72px] lg:pt-0">
            <slot />
        </main>
    </div>
</template>