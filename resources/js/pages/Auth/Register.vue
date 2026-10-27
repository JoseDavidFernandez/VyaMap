<script setup lang="ts">
import { useForm, Link, usePage } from '@inertiajs/vue3';
import AuthShell from '../../components/ui/AuthShell.vue';

const uploadsEnabled = usePage().props.features.uploads;

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
    <AuthShell
        title="Create account"
        description="Create your space to build and explore your travel history."
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="name" class="vyamap-label">Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    required
                    class="vyamap-input"
                    :aria-invalid="form.errors.name ? 'true' : undefined"
                />
                <p v-if="form.errors.name" class="vyamap-error">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="vyamap-label">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="vyamap-input"
                    :aria-invalid="form.errors.email ? 'true' : undefined"
                />
                <p v-if="form.errors.email" class="vyamap-error">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="vyamap-label">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="vyamap-input"
                    aria-describedby="password-help"
                    :aria-invalid="form.errors.password ? 'true' : undefined"
                />
                <p v-if="form.errors.password" class="vyamap-error">{{ form.errors.password }}</p>
                <p v-else id="password-help" class="vyamap-help">
                    At least 10 characters, not found in known data breaches.
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="vyamap-label">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="vyamap-input"
                />
            </div>

            <div v-if="uploadsEnabled">
                <label for="avatar" class="vyamap-label">Profile photo</label>
                <input
                    id="avatar"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="vyamap-input file:mr-3 file:rounded-[0.5rem] file:border-0 file:bg-[var(--vyamap-surface-raised)] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-[var(--vyamap-text)]"
                    @change="handleAvatarChange"
                />
                <p class="vyamap-help">Optional. JPG, PNG or WebP, maximum 5 MB.</p>
                <p v-if="form.errors.avatar" class="vyamap-error">{{ form.errors.avatar }}</p>
            </div>

            <button type="submit" :disabled="form.processing" class="vyamap-button w-full !min-h-11">
                {{ form.processing ? 'Creating account...' : 'Create account' }}
            </button>
        </form>

        <template #footer>
            Already have an account?
            <Link href="/login" class="vyamap-link ml-1">Sign in</Link>
        </template>
    </AuthShell>
</template>
