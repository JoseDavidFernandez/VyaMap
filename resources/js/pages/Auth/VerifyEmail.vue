<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';

defineProps<{
    email: string;
    status?: string | null;
}>();

const form = useForm({});

const resend = () => {
    form.post('/email/verification-notification', {
        preserveScroll: true,
    });
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <main class="min-h-screen bg-[var(--vyamap-background)] text-[var(--vyamap-text)]">
        <div
            class="mx-auto flex min-h-screen max-w-[var(--vyamap-content-width)] flex-col px-6 py-6 sm:px-8 lg:px-10"
        >
            <!-- HEADER -->
            <header>
                <span class="text-lg font-semibold tracking-[-0.04em]">
                    VyaMap
                </span>
            </header>

            <div class="flex flex-1 items-center justify-center py-16">
                <div class="w-full max-w-[420px]">

                    <div class="mb-10">
                        <p class="vyamap-eyebrow">
                            One more step
                        </p>

                        <h1 class="mt-3 text-4xl font-semibold tracking-[-0.06em] sm:text-5xl">
                            Verify your email
                        </h1>

                        <p class="mt-4 text-sm leading-6 vyamap-muted">
                            We have sent a verification link to
                            <span class="text-white/80">{{ email }}</span>.
                            Open it to start using VyaMap. The link expires in 60 minutes.
                        </p>
                    </div>

                    <p
                        v-if="status === 'verification-link-sent'"
                        role="status"
                        class="mb-6 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 text-sm leading-6"
                    >
                        A new verification link has been sent.
                    </p>

                    <!-- RESEND -->
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="vyamap-button w-full"
                        @click="resend"
                    >
                        {{ form.processing ? 'Sending...' : 'Resend verification link' }}
                    </button>

                    <!-- LOGOUT -->
                    <p class="mt-8 text-center text-xs vyamap-muted">
                        Wrong account?

                        <button
                            type="button"
                            class="ml-1 text-white/65 transition hover:text-white"
                            @click="logout"
                        >
                            Sign out
                        </button>
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>
