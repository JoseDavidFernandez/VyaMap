<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <main class="min-h-screen bg-[var(--vyamap-background)] text-[var(--vyamap-text)]">
        <div
            class="mx-auto flex min-h-screen max-w-[var(--vyamap-content-width)] flex-col px-6 py-6 sm:px-8 lg:px-10"
        >
            <!-- HEADER -->
            <header>
                <Link
                    href="/login"
                    class="text-lg font-semibold tracking-[-0.04em]"
                >
                    VyaMap
                </Link>
            </header>

            <!-- FORM -->
            <div class="flex flex-1 items-center justify-center py-16">
                <div class="w-full max-w-[420px]">

                    <div class="mb-10">
                        <p class="vyamap-eyebrow">
                            Account recovery
                        </p>

                        <h1 class="mt-3 text-4xl font-semibold tracking-[-0.06em] sm:text-5xl">
                            New password
                        </h1>

                        <p class="mt-4 text-sm leading-6 vyamap-muted">
                            Choose a new password for your account.
                        </p>
                    </div>

                    <form
                        @submit.prevent="submit"
                        class="space-y-5"
                    >
                        <!-- EMAIL -->
                        <div>
                            <label
                                for="email"
                                class="vyamap-label"
                            >
                                Email
                            </label>

                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                class="vyamap-input"
                            />

                            <p
                                v-if="form.errors.email"
                                class="vyamap-error"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- PASSWORD -->
                        <div>
                            <label
                                for="password"
                                class="vyamap-label"
                            >
                                New password
                            </label>

                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="vyamap-input"
                            />

                            <p
                                v-if="form.errors.password"
                                class="vyamap-error"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- CONFIRMATION -->
                        <div>
                            <label
                                for="password_confirmation"
                                class="vyamap-label"
                            >
                                Confirm new password
                            </label>

                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="vyamap-input"
                            />
                        </div>

                        <p
                            v-if="form.errors.token"
                            class="vyamap-error"
                        >
                            {{ form.errors.token }}
                        </p>

                        <!-- SUBMIT -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="vyamap-button mt-2 w-full"
                        >
                            {{ form.processing ? 'Saving...' : 'Reset password' }}
                        </button>
                    </form>

                    <!-- REQUEST AGAIN -->
                    <p class="mt-8 text-center text-xs vyamap-muted">
                        Link expired?

                        <Link
                            href="/forgot-password"
                            class="ml-1 text-white/65 transition hover:text-white"
                        >
                            Request a new one
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>
