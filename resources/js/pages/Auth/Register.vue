<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    avatar: null as File | null,
});

const handleAvatarChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (target.files && target.files[0]) {
        form.avatar = target.files[0];
    }
};

const submit = () => {
    form.post('/register', {
        forceFormData: true,
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
                            Start your journey
                        </p>

                        <h1 class="mt-3 text-4xl font-semibold tracking-[-0.06em] sm:text-5xl">
                            Create account
                        </h1>

                        <p class="mt-4 text-sm leading-6 vyamap-muted">
                            Create your space to build and explore your travel history.
                        </p>
                    </div>

                    <form
                        @submit.prevent="submit"
                        class="space-y-5"
                    >
                        <!-- NAME -->
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
                                autocomplete="name"
                                required
                                class="vyamap-input"
                            />

                            <p
                                v-if="form.errors.name"
                                class="vyamap-error"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

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

                        <!-- CONFIRM PASSWORD -->
                        <div>
                            <label
                                for="password_confirmation"
                                class="vyamap-label"
                            >
                                Confirm password
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

                        <!-- AVATAR -->
                        <div>
                            <label
                                for="avatar"
                                class="vyamap-label"
                            >
                                Profile photo
                            </label>

                            <input
                                id="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="handleAvatarChange"
                                class="vyamap-input"
                            />

                            <p class="mt-2 text-xs vyamap-muted">
                                Optional. JPG, PNG or WebP, maximum 5 MB.
                            </p>

                            <p
                                v-if="form.errors.avatar"
                                class="vyamap-error"
                            >
                                {{ form.errors.avatar }}
                            </p>
                        </div>

                        <!-- SUBMIT -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="vyamap-button mt-2 w-full"
                        >
                            {{ form.processing ? 'Creating account...' : 'Create account' }}
                        </button>
                    </form>

                    <!-- LOGIN -->
                    <p class="mt-8 text-center text-xs vyamap-muted">
                        Already have an account?

                        <Link
                            href="/login"
                            class="ml-1 text-white/65 transition hover:text-white"
                        >
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>

            
        </div>
    </main>
</template>