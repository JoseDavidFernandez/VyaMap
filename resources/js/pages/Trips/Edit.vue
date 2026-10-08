<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string;
    end_date: string;
}

const props = defineProps<{
    trip: Trip;
}>();

const form = useForm({
    name: props.trip.name,
    description: props.trip.description ?? '',
    start_date: props.trip.start_date,
    end_date: props.trip.end_date,
});

const submit = () => {
    form.put(`/trips/${props.trip.id}`);
};
</script>

<template>
    <Head :title="`Edit ${trip.name}`" />

    <div class="mx-auto max-w-5xl px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
        <!-- Header -->
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
                <p
                    class="text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-300/80"
                >
                    Trip settings
                </p>

                <h1
                    class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl"
                >
                    Edit trip
                </h1>

                <p
                    class="mt-3 max-w-2xl text-sm leading-6 text-white/55 sm:text-base"
                >
                    Update the information of your trip.
                </p>
            </div>
        </div>

        <!-- Form -->
        <form
            class="mt-6 rounded-[30px] border border-white/[0.08] bg-[#111620] p-5 sm:p-8"
            @submit.prevent="submit"
        >
            <div class="space-y-6">
                <!-- Name -->
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium text-white/80"
                    >
                        Trip name
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        maxlength="150"
                        required
                        class="w-full rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 text-sm text-white outline-none transition placeholder:text-white/25 focus:border-cyan-300/40 focus:bg-white/[0.05]"
                        placeholder="e.g. Italy 2026"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-2 text-xs text-red-400"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label
                        for="description"
                        class="mb-2 block text-sm font-medium text-white/80"
                    >
                        Description
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="5"
                        class="w-full resize-none rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 text-sm leading-6 text-white outline-none transition placeholder:text-white/25 focus:border-cyan-300/40 focus:bg-white/[0.05]"
                        placeholder="Add a short description of your trip..."
                    ></textarea>

                    <p
                        v-if="form.errors.description"
                        class="mt-2 text-xs text-red-400"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Dates -->
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label
                            for="start_date"
                            class="mb-2 block text-sm font-medium text-white/80"
                        >
                            Start date
                        </label>

                        <input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            required
                            class="w-full rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/40 focus:bg-white/[0.05]"
                        />

                        <p
                            v-if="form.errors.start_date"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.start_date }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="end_date"
                            class="mb-2 block text-sm font-medium text-white/80"
                        >
                            End date
                        </label>

                        <input
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                            required
                            class="w-full rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/40 focus:bg-white/[0.05]"
                        />

                        <p
                            v-if="form.errors.end_date"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.end_date }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div
                class="mt-8 flex flex-col-reverse gap-3 border-t border-white/[0.06] pt-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link
                    :href="`/trips/${trip.id}`"
                    class="inline-flex items-center justify-center rounded-full border border-white/[0.08] px-5 py-3 text-sm font-medium text-white/65 transition hover:border-white/[0.15] hover:bg-white/[0.04] hover:text-white"
                >
                    Cancel
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-[#0d1119] transition hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </form>
    </div>
</template>