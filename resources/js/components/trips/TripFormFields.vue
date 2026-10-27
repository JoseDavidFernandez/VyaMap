<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

/** Campos comunes de crear y editar viaje. */
defineProps<{
    form: InertiaForm<{
        name: string;
        description: string;
        start_date: string;
        end_date: string;
    }>;
    namePlaceholder?: string;
}>();
</script>

<template>
    <div class="space-y-5">
        <div>
            <label for="name" class="vyamap-label">Name</label>
            <input
                id="name"
                v-model="form.name"
                type="text"
                maxlength="150"
                autocomplete="off"
                class="vyamap-input"
                :placeholder="namePlaceholder ?? 'e.g. Balkans 2026'"
                :aria-invalid="form.errors.name ? 'true' : undefined"
            />
            <p v-if="form.errors.name" class="vyamap-error">{{ form.errors.name }}</p>
        </div>

        <div>
            <label for="description" class="vyamap-label">
                Description <span class="text-[var(--vyamap-text-subtle)]">(optional)</span>
            </label>
            <textarea
                id="description"
                v-model="form.description"
                rows="4"
                class="vyamap-textarea"
                placeholder="A short description of the trip..."
                :aria-invalid="form.errors.description ? 'true' : undefined"
            ></textarea>
            <p v-if="form.errors.description" class="vyamap-error">{{ form.errors.description }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="start_date" class="vyamap-label">Start date</label>
                <input
                    id="start_date"
                    v-model="form.start_date"
                    type="date"
                    class="vyamap-input"
                    :aria-invalid="form.errors.start_date ? 'true' : undefined"
                />
                <p v-if="form.errors.start_date" class="vyamap-error">{{ form.errors.start_date }}</p>
            </div>

            <div>
                <label for="end_date" class="vyamap-label">End date</label>
                <input
                    id="end_date"
                    v-model="form.end_date"
                    type="date"
                    class="vyamap-input"
                    :min="form.start_date || undefined"
                    :aria-invalid="form.errors.end_date ? 'true' : undefined"
                />
                <p v-if="form.errors.end_date" class="vyamap-error">{{ form.errors.end_date }}</p>
            </div>
        </div>
    </div>
</template>
