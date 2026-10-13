<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';

defineProps<{
    status?: string | null;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password', {
        preserveScroll: true,
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
                            Forgot password
                        </h1>

                        <p class="mt-4 text-sm leading-6 vyamap-muted">
                            Enter the email address of your account and we will send you a link to choose a new password.
                        </p>
                    </div>

                    <p
                        v-if="status"
                        role="status"
                        class="mb-6 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 text-sm leading-6"
                    >
                        {{ status }}
                    </p>

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

                        <!-- SUBMIT -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="vyamap-button mt-2 w-full"
                        >
                            {{ form.processing ? 'Sending...' : 'Send reset link' }}
                        </button>
                    </form>

                    <!-- LOGIN -->
                    <p class="mt-8 text-center text-xs vyamap-muted">
                        Remembered it?

                        <Link
                            href="/login"
                            class="ml-1 text-white/65 transition hover:text-white"
                        >
                            Back to sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>
