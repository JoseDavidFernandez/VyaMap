<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import AuthShell from '../../components/ui/AuthShell.vue';
import Icon from '../../components/ui/Icon.vue';

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
    <AuthShell
        title="Verify your email"
        :status="status === 'verification-link-sent' ? 'A new verification link has been sent.' : null"
    >
        <div class="flex gap-4">
            <span class="vyamap-empty-icon !h-11 !w-11 shrink-0">
                <Icon name="mail" :size="20" />
            </span>
            <p class="text-sm text-[var(--vyamap-text-muted)]">
                We have sent a verification link to
                <span class="font-medium text-[var(--vyamap-text)]">{{ email }}</span>.
                Open it to start using VyaMap. The link expires in 60 minutes.
            </p>
        </div>

        <button
            type="button"
            :disabled="form.processing"
            class="vyamap-button mt-6 w-full !min-h-11"
            @click="resend"
        >
            {{ form.processing ? 'Sending...' : 'Resend verification link' }}
        </button>

        <template #footer>
            Wrong account?
            <button type="button" class="vyamap-link ml-1" @click="logout">Sign out</button>
        </template>
    </AuthShell>
</template>
