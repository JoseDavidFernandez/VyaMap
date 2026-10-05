<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

const form = useForm({
    name: '',
    description: '',
    start_date: '',
    end_date: '',
});

const submit = () => {
    form.post('/trips');
};
</script>

<template>
    <AppLayout title="Create trip">
        <div
            class="mx-auto max-w-3xl px-6 py-10 lg:px-8"
        >
            <!-- Back -->
            <Link
                href="/"
                class="vyamap-link inline-flex items-center gap-2"
            >
                <span aria-hidden="true">←</span>
                My history
            </Link>

            <!-- Header -->
            <header class="mt-8">
                <p class="vyamap-eyebrow">
                    New trip
                </p>

                <h1
                    class="mt-3 text-4xl font-semibold tracking-[-0.06em]"
                >
                    Create a trip
                </h1>

                <p class="mt-4 text-base leading-7 vyamap-muted">
                    Start building your travel history.
                </p>
            </header>

            <!-- Form -->
            <form
                class="mt-10 space-y-8"
                @submit.prevent="submit"
            >
                <!-- Name -->
                <div>
                    <label
                        for="name"
                        class="vyamap-label"
                    >
                        Name
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        maxlength="150"
                        autocomplete="off"
                        class="vyamap-input"
                        placeholder="e.g. Balkans 2026"
                    />

                    <p
                        v-if="form.errors.name"
                        class="vyamap-error"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label
                        for="description"
                        class="vyamap-label"
                    >
                        Description
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        class="vyamap-input resize-none"
                        placeholder="A short description of the trip..."
                    />

                    <p
                        v-if="form.errors.description"
                        class="vyamap-error"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Dates -->
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label
                            for="start_date"
                            class="vyamap-label"
                        >
                            Start date
                        </label>

                        <input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            class="vyamap-input"
                        />

                        <p
                            v-if="form.errors.start_date"
                            class="vyamap-error"
                        >
                            {{ form.errors.start_date }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="end_date"
                            class="vyamap-label"
                        >
                            End date
                        </label>

                        <input
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                            class="vyamap-input"
                        />

                        <p
                            v-if="form.errors.end_date"
                            class="vyamap-error"
                        >
                            {{ form.errors.end_date }}
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div
                    class="flex items-center justify-end gap-4 border-t border-[var(--vyamap-border)] pt-6"
                >
                    <Link
                        href="/"
                        class="vyamap-button-secondary"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="vyamap-button"
                    >
                        {{
                            form.processing
                                ? 'Creating...'
                                : 'Create trip'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>