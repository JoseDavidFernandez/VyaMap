<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface User {
    name: string;
    email: string;
    avatar: string | null;
}

const props = defineProps<{
    user: User;
}>();

const uploadsEnabled = usePage().props.features.uploads;

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
        <div class="vyamap-container max-w-2xl">
            <Link href="/profile" class="vyamap-button-ghost vyamap-button-sm -ml-3 mb-4">
                <Icon name="arrow-left" :size="16" />
                Profile
            </Link>

            <header class="vyamap-page-header !mb-6">
                <div>
                    <h1 class="vyamap-page-title">Edit profile</h1>
                    <p class="vyamap-page-description">Update your profile information.</p>
                </div>
            </header>

            <form class="vyamap-card p-5 sm:p-7" @submit.prevent="submit">
                <!-- AVATAR -->
                <div class="flex items-center gap-5 border-b border-[var(--vyamap-border)] pb-6">
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-full border-2 border-[rgba(34,211,238,0.45)] bg-[var(--vyamap-surface-raised)]">
                        <img v-if="avatarPreview" :src="avatarPreview" alt="" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center text-2xl font-semibold">
                            {{ form.name.charAt(0).toUpperCase() }}
                        </div>
                    </div>

                    <div v-if="uploadsEnabled">
                        <label for="profile-avatar" class="vyamap-button-secondary vyamap-button-sm cursor-pointer">
                            <Icon name="image-plus" :size="16" />
                            Change photo
                        </label>
                        <input
                            id="profile-avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="handleAvatarChange"
                        />
                        <p class="vyamap-help">JPG, PNG or WebP, maximum 5 MB.</p>
                    </div>
                    <p v-else class="text-sm text-[var(--vyamap-text-muted)]">Profile photos are turned off on this server.</p>
                </div>
                <p v-if="form.errors.avatar" class="vyamap-error">{{ form.errors.avatar }}</p>

                <!-- NOMBRE -->
                <div class="mt-6">
                    <label for="profile-name" class="vyamap-label">Name</label>
                    <input
                        id="profile-name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        class="vyamap-input"
                        :aria-invalid="form.errors.name ? 'true' : undefined"
                    />
                    <p v-if="form.errors.name" class="vyamap-error">{{ form.errors.name }}</p>
                </div>

                <div class="mt-4">
                    <label for="profile-email" class="vyamap-label">Email</label>
                    <input id="profile-email" :value="props.user.email" type="email" class="vyamap-input" disabled />
                    <p class="vyamap-help">The sign-in email cannot be changed here.</p>
                </div>

                <div class="mt-7 flex flex-col-reverse gap-3 border-t border-[var(--vyamap-border)] pt-5 sm:flex-row sm:justify-end">
                    <Link href="/profile" class="vyamap-button-secondary">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="vyamap-button">
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
