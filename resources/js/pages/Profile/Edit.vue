<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface User {
    name: string;
    email: string;
    avatar: string | null;
}

const props = defineProps<{
    user: User;
}>();

const form = useForm({
    name: props.user.name,
    avatar: null as File | null,
    _method: 'put',
});

const avatarPreview = ref<string | null>(
    props.user.avatar
        ? `/storage/${props.user.avatar}`
        : null,
);

const handleAvatarChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (!target.files || !target.files[0]) {
        return;
    }

    const file = target.files[0];

    form.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const submit = () => {
    form.post('/profile', {
        forceFormData: true,
    });
};
</script>

<template>
    <AppLayout title="Edit profile">
        <div class="vyamap-page">
            <main
                class="mx-auto max-w-[900px] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >
                <!-- HEADER -->

                <header class="mb-10">
                    <Link
                        href="/profile"
                        class="text-sm text-[var(--vyamap-text-muted)] transition hover:text-[var(--vyamap-text)]"
                    >
                        ← Profile
                    </Link>

                    <h1
                        class="mt-5 text-3xl font-semibold tracking-tight"
                    >
                        Edit profile
                    </h1>

                    <p
                        class="mt-2 text-sm text-[var(--vyamap-text-muted)]"
                    >
                        Update your profile information.
                    </p>
                </header>

                <!-- FORM -->

                <form
                    @submit.prevent="submit"
                    class="vyamap-card-lg p-6 sm:p-8"
                >
                    <!-- AVATAR -->

                    <div
                        class="flex flex-col items-center border-b border-[var(--vyamap-border)] pb-8"
                    >
                        <div
                            class="h-28 w-28 overflow-hidden rounded-full bg-[var(--vyamap-surface-strong)]"
                        >
                            <img
                                v-if="avatarPreview"
                                :src="avatarPreview"
                                :alt="form.name"
                                class="h-full w-full object-cover"
                            />

                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-2xl font-semibold"
                            >
                                {{ form.name.charAt(0).toUpperCase() }}
                            </div>
                        </div>

                        <label
                            for="profile-avatar"
                            class="mt-4 cursor-pointer text-sm font-medium transition hover:opacity-70"
                        >
                            Change photo
                        </label>

                        <input
                            id="profile-avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="handleAvatarChange"
                            class="sr-only"
                        />

                        <p
                            class="mt-2 text-xs text-[var(--vyamap-text-muted)]"
                        >
                            JPG, PNG or WebP, maximum 5 MB.
                        </p>

                        <p
                            v-if="form.errors.avatar"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.avatar }}
                        </p>
                    </div>

                    <!-- NAME -->

                    <div class="mt-8">
                        <label
                            for="profile-name"
                            class="block text-sm font-medium"
                        >
                            Name
                        </label>

                        <input
                            id="profile-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            class="mt-2 w-full rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-border-strong)]"
                        />

                        <p
                            v-if="form.errors.name"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- ACTIONS -->

                    <div
                        class="mt-8 flex items-center justify-end gap-3 border-t border-[var(--vyamap-border)] pt-6"
                    >
                        <Link
                            href="/profile"
                            class="rounded-xl px-4 py-2.5 text-sm font-medium text-[var(--vyamap-text-muted)] transition hover:text-[var(--vyamap-text)]"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-[var(--vyamap-text)] px-5 py-2.5 text-sm font-medium text-[var(--vyamap-background)] transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : 'Save changes'
                            }}
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </AppLayout>
</template>