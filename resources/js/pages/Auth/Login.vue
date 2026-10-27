<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import AuthShell from '../../components/ui/AuthShell.vue';

defineProps<{
    status?: string | null;
    canRegister?: boolean;
}>();

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
    <AuthShell
        title="Sign in"
        description="Continue building and exploring your travel history."
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
                    aria-describedby="email-error"
                />
                <p v-if="form.errors.email" id="email-error" class="vyamap-error">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-4">
                    <label for="password" class="vyamap-label">Password</label>
                    <Link href="/forgot-password" class="vyamap-link mb-2 text-[0.8125rem]">
                        Forgot password?
                    </Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="vyamap-input"
                    :aria-invalid="form.errors.password ? 'true' : undefined"
                />
                <p v-if="form.errors.password" class="vyamap-error">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex w-fit cursor-pointer items-center gap-3 text-sm text-[var(--vyamap-text-muted)]">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 accent-[var(--vyamap-cyan)]"
                />
                Remember me
            </label>

            <button type="submit" :disabled="form.processing" class="vyamap-button w-full !min-h-11">
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>

        <template v-if="canRegister" #footer>
            Don't have an account?
            <Link href="/register" class="vyamap-link ml-1">Create one</Link>
        </template>
    </AuthShell>
</template>
