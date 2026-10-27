<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import AuthShell from '../../components/ui/AuthShell.vue';

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
    <AuthShell
        title="Forgot password"
        description="Enter the email address of your account and we will send you a link to choose a new password."
        :status="status"
    >
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

            <button type="submit" :disabled="form.processing" class="vyamap-button w-full !min-h-11">
                {{ form.processing ? 'Sending...' : 'Send reset link' }}
            </button>
        </form>

        <template #footer>
            Remembered it?
            <Link href="/login" class="vyamap-link ml-1">Back to sign in</Link>
        </template>
    </AuthShell>
</template>
