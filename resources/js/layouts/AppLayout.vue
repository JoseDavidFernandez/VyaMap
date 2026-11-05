<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import Navbar from '../components/Navbar.vue';
import Icon from '../components/ui/Icon.vue';
import { useReadOnly } from '../composables/useReadOnly';

defineProps<{
    title?: string;
}>();

const page = usePage();
const readOnly = useReadOnly();

// Aviso del servidor si acaba de rechazar una escritura de la cuenta de demostración.
const demoNotice = computed(() => page.props.flash?.demoNotice ?? null);
</script>

<template>
    <div class="min-h-screen text-[var(--vyamap-text)]">
        <Head v-if="title" :title="title" />

        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-[var(--vyamap-radius-sm)] focus:bg-[var(--vyamap-cyan)] focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#041019]"
        >
            Skip to content
        </a>

        <Navbar />

        <main
            id="main"
            class="min-h-screen pb-[calc(5rem+env(safe-area-inset-bottom))] pt-[calc(3.5rem+env(safe-area-inset-top))] lg:pb-0 lg:pl-[72px] lg:pt-0 xl:pl-[var(--vyamap-nav-width)]"
        >
            <!-- Cuenta de demostración: siempre visible; si el servidor acaba de rechazar algo, como alerta. -->
            <div v-if="readOnly" class="vyamap-container pt-4" data-demo-banner>
                <p
                    :role="demoNotice ? 'alert' : 'status'"
                    class="flex items-start gap-3 rounded-[var(--vyamap-radius-md)] border px-4 py-2.5 text-sm"
                    :class="demoNotice
                        ? 'border-[rgba(251,191,36,0.55)] bg-[rgba(251,191,36,0.12)]'
                        : 'border-[var(--vyamap-border-strong)] bg-[var(--vyamap-surface)]'"
                >
                    <Icon name="lock" :size="16" class="mt-0.5 text-[var(--vyamap-warning)]" />
                    <span>
                        <strong class="font-semibold">{{ demoNotice ?? 'This is a read-only demo account.' }}</strong>
                        <span class="text-[var(--vyamap-text-muted)]"> You can explore everything, but changes are disabled.</span>
                    </span>
                </p>
            </div>

            <slot />
        </main>
    </div>
</template>
