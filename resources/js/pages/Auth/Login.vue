<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login');
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
                            Welcome back
                        </p>

                        <h1 class="mt-3 text-4xl font-semibold tracking-[-0.06em] sm:text-5xl">
                            Sign in
                        </h1>

                        <p class="mt-4 text-sm leading-6 vyamap-muted">
                            Continue building and exploring your travel history.
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
                                Password
                            </label>

                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
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

                        <!-- OPTIONS -->
                        <label class="flex cursor-pointer items-center gap-3 pt-1">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-white/20 bg-white/5 accent-white"
                            />

                            <span class="text-xs vyamap-muted">
                                Remember me
                            </span>
                        </label>

                        <!-- SUBMIT -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="vyamap-button mt-2 w-full"
                        >
                            {{ form.processing ? 'Signing in...' : 'Sign in' }}
                        </button>
                    </form>

                    <!-- REGISTER -->
                    <p class="mt-8 text-center text-xs vyamap-muted">
                        Don't have an account?

                        <Link
                            href="/register"
                            class="ml-1 text-white/65 transition hover:text-white"
                        >
                            Create one
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>