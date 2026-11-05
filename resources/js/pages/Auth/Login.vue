<script setup lang="ts">
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AuthShell from '../../components/ui/AuthShell.vue';

defineProps<{
    status?: string | null;
    canRegister?: boolean;
    // Solo si DEMO_USER_EMAIL está configurado y la cuenta existe.
    canTryDemo?: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login');
};

// El servidor inicia la sesión de la cuenta de demostración: no se envía ninguna credencial.
const demoForm = useForm({});
// Error del límite de frecuencia (el formulario no tiene campos propios).
const demoError = computed(() => (demoForm.errors as Record<string, string | undefined>).demo);

const tryDemo = () => {
    demoForm.post('/demo-login');
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

        <div v-if="canTryDemo" class="mt-6">
            <div class="mb-5 flex items-center gap-3 text-[0.8125rem] text-[var(--vyamap-text-subtle)]" aria-hidden="true">
                <span class="h-px flex-1 bg-[var(--vyamap-border)]" />
                or
                <span class="h-px flex-1 bg-[var(--vyamap-border)]" />
            </div>
            <button
                type="button"
                class="vyamap-button-secondary w-full !min-h-11"
                :disabled="demoForm.processing"
                aria-describedby="demo-help"
                @click="tryDemo"
            >
                {{ demoForm.processing ? 'Opening the demo...' : 'Try the demo' }}
            </button>
            <p v-if="demoError" role="alert" class="vyamap-error">{{ demoError }}</p>
            <p v-else id="demo-help" class="vyamap-help mt-2 text-center">Explore a sample account. It is read-only.</p>
        </div>

        <template v-if="canRegister" #footer>
            Don't have an account?
            <Link href="/register" class="vyamap-link ml-1">Create one</Link>
        </template>
    </AuthShell>
</template>
