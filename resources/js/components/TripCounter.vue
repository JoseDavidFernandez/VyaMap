<script setup lang="ts">
defineProps<{
    trips: number;
}>();
</script>

<template>
    <div>
        <p>Trips: {{ trips }}</p>
    </div>
</template>