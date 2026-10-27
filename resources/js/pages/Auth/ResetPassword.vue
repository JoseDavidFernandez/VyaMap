<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import AuthShell from '../../components/ui/AuthShell.vue';

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
    <AuthShell title="New password" description="Choose a new password for your account.">
        <form class="space-y-5" @submit.prevent="submit">
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
                <label for="password" class="vyamap-label">New password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="vyamap-input"
                    :aria-invalid="form.errors.password ? 'true' : undefined"
                />
                <p v-if="form.errors.password" class="vyamap-error">{{ form.errors.password }}</p>
                <p v-else class="vyamap-help">At least 10 characters, not found in known data breaches.</p>
            </div>

            <div>
                <label for="password_confirmation" class="vyamap-label">Confirm new password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="vyamap-input"
                />
            </div>

            <p v-if="form.errors.token" class="vyamap-error">{{ form.errors.token }}</p>

            <button type="submit" :disabled="form.processing" class="vyamap-button w-full !min-h-11">
                {{ form.processing ? 'Saving...' : 'Reset password' }}
            </button>
        </form>

        <template #footer>
            Link expired?
            <Link href="/forgot-password" class="vyamap-link ml-1">Request a new one</Link>
        </template>
    </AuthShell>
</template>
