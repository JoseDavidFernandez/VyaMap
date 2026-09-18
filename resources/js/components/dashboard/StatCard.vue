<script setup lang="ts">
defineProps<{
    value: number;
    label: string;
}>();
</script>

<template>
    <div>
        <strong>{{ value }}</strong>
        <span>{{ label }}</span>
    </div>
</template>