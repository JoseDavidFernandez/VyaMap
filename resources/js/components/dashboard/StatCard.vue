<script setup lang="ts">
defineProps<{
    value: number;
    label: string;
}>();
</script>

<template>
    <div class="min-w-0">
        <p class="text-3xl font-semibold tracking-tight text-[var(--vyamap-text)]">
            {{ value }}
        </p>

        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">
            {{ label }}
        </p>
    </div>
</template>