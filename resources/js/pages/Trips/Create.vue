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
        <div class="mx-auto max-w-3xl px-6 py-10 lg:px-8">
            <!-- Back -->
            <Link
                href="/"
                class="inline-flex items-center gap-2 text-sm font-medium text-[var(--vyamap-text-muted)] transition-colors hover:text-[var(--vyamap-text)]"
            >
                <span aria-hidden="true">←</span>
                My history
            </Link>

            <!-- Header -->
            <header class="mt-8">
                <p
                    class="text-sm font-medium uppercase tracking-wider text-[var(--vyamap-text-muted)]"
                >
                    New trip
                </p>

                <h1
                    class="mt-2 text-4xl font-semibold tracking-tight"
                >
                    Create a trip
                </h1>

                <p
                    class="mt-3 text-base leading-7 text-[var(--vyamap-text-muted)]"
                >
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
                        class="block text-sm font-medium"
                    >
                        Name
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        maxlength="150"
                        autocomplete="off"
                        class="mt-2 block w-full rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text)]"
                        placeholder="e.g. Balkans 2026"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-2 text-sm text-red-600"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label
                        for="description"
                        class="block text-sm font-medium"
                    >
                        Description
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        class="mt-2 block w-full resize-none rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text)]"
                        placeholder="A short description of the trip..."
                    />

                    <p
                        v-if="form.errors.description"
                        class="mt-2 text-sm text-red-600"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Dates -->
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label
                            for="start_date"
                            class="block text-sm font-medium"
                        >
                            Start date
                        </label>

                        <input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            class="mt-2 block w-full rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text)]"
                        />

                        <p
                            v-if="form.errors.start_date"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ form.errors.start_date }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="end_date"
                            class="block text-sm font-medium"
                        >
                            End date
                        </label>

                        <input
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                            class="mt-2 block w-full rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text)]"
                        />

                        <p
                            v-if="form.errors.end_date"
                            class="mt-2 text-sm text-red-600"
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
                        class="rounded-full px-4 py-2 text-sm font-medium text-[var(--vyamap-text-muted)] transition-colors hover:text-[var(--vyamap-text)]"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-full bg-[var(--vyamap-text)] px-5 py-2.5 text-sm font-medium text-white transition-opacity disabled:cursor-not-allowed disabled:opacity-50"
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