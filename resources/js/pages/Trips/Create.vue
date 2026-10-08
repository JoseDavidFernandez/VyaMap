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
        <div class="vyamap-page">
            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >
                <!-- HEADER -->
                <section class="mb-8">
                    <div
                        class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-6 py-9 sm:px-10 sm:py-11 lg:px-12 lg:py-12"
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
                                Travel history
                            </div>

                            <div class="mt-3">
                                <div
                                    class="text-[10px] uppercase tracking-[0.22em] text-white/35"
                                >
                                    New trip
                                </div>

                                <h1
                                    class="mt-2 text-3xl font-semibold tracking-[-0.05em] text-white sm:text-4xl"
                                >
                                    Create a trip
                                </h1>

                                <p
                                    class="mt-3 max-w-2xl text-sm leading-6 text-white/35"
                                >
                                    Start building your travel history.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- FORM -->
                <section class="mx-auto max-w-3xl">
                    <div
                        class="rounded-[30px] border border-white/[0.08] bg-white/[0.02] p-6 sm:p-8 lg:p-10"
                    >
                        <form
                            class="space-y-8"
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
                                ></textarea>

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
                                class="flex flex-col-reverse gap-3 border-t border-[var(--vyamap-border)] pt-6 sm:flex-row sm:items-center sm:justify-end"
                            >
                                <Link
                                    href="/"
                                    class="vyamap-button-secondary text-center"
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
                </section>
            </main>
        </div>
    </AppLayout>
</template>