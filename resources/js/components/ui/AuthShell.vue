<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BrandMark from './BrandMark.vue';

/**
 * Marco común de las pantallas de acceso: marca y rutas a la izquierda (solo
 * escritorio) y el formulario en una tarjeta. `status` muestra el mensaje de
 * sesión (enlace enviado, contraseña cambiada…).
 */
defineProps<{
    title: string;
    description?: string;
    status?: string | null;
}>();
</script>

<template>
    <Head :title="title" />

    <main class="relative grid min-h-screen lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
        <!-- MARCA (escritorio) -->
        <section class="relative hidden overflow-hidden border-r border-[var(--vyamap-border)] lg:flex lg:flex-col lg:justify-between lg:p-12">
            <Link href="/login" class="flex items-center gap-3" aria-label="VyaMap">
                <BrandMark class="vyamap-auth-mark" />
                <span class="text-lg font-semibold tracking-[-0.02em]">VyaMap</span>
            </Link>

            <svg
                class="pointer-events-none absolute inset-x-0 top-1/2 mx-auto w-[110%] max-w-[46rem] -translate-y-1/2 opacity-90"
                viewBox="0 0 600 420"
                fill="none"
                aria-hidden="true"
            >
                <defs>
                    <linearGradient id="vy-route" x1="0" x2="1" y1="0" y2="0">
                        <stop offset="0" stop-color="#22D3EE" />
                        <stop offset="1" stop-color="#A78BFA" />
                    </linearGradient>
                    <radialGradient id="vy-fade" cx="0.5" cy="0.5" r="0.55">
                        <stop offset="0.55" stop-color="#fff" />
                        <stop offset="1" stop-color="#fff" stop-opacity="0" />
                    </radialGradient>
                    <mask id="vy-mask">
                        <rect width="600" height="420" fill="url(#vy-fade)" />
                    </mask>
                </defs>
                <g mask="url(#vy-mask)" stroke="rgba(148,170,214,0.16)" stroke-width="1">
                    <ellipse cx="300" cy="210" rx="250" ry="250" />
                    <ellipse cx="300" cy="210" rx="170" ry="250" />
                    <ellipse cx="300" cy="210" rx="85" ry="250" />
                    <path d="M50 210h500M70 110h460M70 310h460M130 40h340M130 380h340" />
                </g>
                <g stroke="url(#vy-route)" stroke-width="2" stroke-linecap="round">
                    <path d="M150 250C220 120 360 100 440 170" />
                    <path d="M150 250C250 300 360 300 470 260" stroke-dasharray="2 7" />
                    <path d="M440 170C470 190 480 225 470 260" stroke-dasharray="2 7" />
                </g>
                <g fill="#070B14" stroke-width="2">
                    <circle cx="150" cy="250" r="6" stroke="#22D3EE" />
                    <circle cx="440" cy="170" r="6" stroke="#7C9BFF" />
                    <circle cx="470" cy="260" r="6" stroke="#A78BFA" />
                </g>
            </svg>

            <div class="relative max-w-md">
                <p class="text-[clamp(1.75rem,1.2rem+1.6vw,2.5rem)] font-semibold leading-[1.1] tracking-[-0.035em]">
                    Every trip, city and flight on one map.
                </p>
                <p class="mt-3 text-[var(--vyamap-text-muted)]">
                    Record where you have been, plan where you are going and see the distance add up.
                </p>
            </div>
        </section>

        <!-- FORMULARIO -->
        <section class="flex min-h-screen flex-col px-4 py-6 sm:px-8 lg:min-h-0 lg:justify-center lg:px-12">
            <Link href="/login" class="mb-10 flex items-center gap-2.5 lg:hidden" aria-label="VyaMap">
                <BrandMark class="vyamap-auth-mark !h-8 !w-8" />
                <span class="text-base font-semibold tracking-[-0.02em]">VyaMap</span>
            </Link>

            <div class="mx-auto my-auto w-full max-w-[26rem] lg:my-0">
                <h1 class="text-[clamp(1.875rem,1.5rem+1.4vw,2.5rem)] font-semibold leading-[1.1] tracking-[-0.035em]">
                    {{ title }}
                </h1>

                <p v-if="description" class="mt-3 text-[var(--vyamap-text-muted)]">
                    {{ description }}
                </p>

                <p
                    v-if="status"
                    role="status"
                    class="mt-6 rounded-[var(--vyamap-radius-md)] border border-[rgba(34,211,238,0.35)] bg-[rgba(34,211,238,0.08)] px-4 py-3 text-sm text-[var(--vyamap-text)]"
                >
                    {{ status }}
                </p>

                <div class="vyamap-card mt-8 p-5 sm:p-6">
                    <slot />
                </div>

                <div v-if="$slots.footer" class="mt-6 text-center text-sm text-[var(--vyamap-text-muted)]">
                    <slot name="footer" />
                </div>
            </div>
        </section>
    </main>
</template>

<style>
.vyamap-auth-mark {
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
}
</style>
