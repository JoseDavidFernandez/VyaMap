<script setup lang="ts">
import Navbar from '../components/Navbar.vue';


defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-[var(--vyamap-background)] text-[var(--vyamap-text)]">
        <Navbar />

        <main>
            <slot />
        </main>
    </div>
</template>