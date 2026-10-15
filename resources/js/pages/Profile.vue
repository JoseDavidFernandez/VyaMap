<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { countCountries, type CountryStatus } from '../utils/countryStatus';

// El globo (three.js) se carga solo al abrir el perfil: no pesa en el resto de páginas.
const ProfileTravelGlobe = defineAsyncComponent(
    () => import('../components/ProfileTravelGlobe.vue'),
);

interface User {
    name: string;
    email: string;
    avatar: string | null;
    member_since: string | null;
}

interface Country {
    name: string;
    iso_code: string;
    country_status: CountryStatus;
}

/*
 * Página de la cuenta. Las cifras generales están en el dashboard, el
 * progreso geográfico en Passport y la consulta de países en Countries.
 */
const props = defineProps<{
    user: User;
    countries: Country[];
}>();

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

const avatarUrl = computed(() => {
    const avatar = props.user.avatar;

    if (!avatar) {
        return null;
    }

    return avatar.startsWith('/') ||
        avatar.startsWith('http://') ||
        avatar.startsWith('https://')
        ? avatar
        : `/storage/${avatar}`;
});

const memberSince = computed(() => {
    if (!props.user.member_since) {
        return null;
    }

    return new Intl.DateTimeFormat('en-GB', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(props.user.member_since));
});

const countryTotals = computed(() => countCountries(props.countries));
</script>

<template>
    <AppLayout title="Profile">
        <div class="min-h-screen bg-[var(--vyamap-background)]">
            <main class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10">

                <!-- ACCOUNT -->
                <section class="mb-7">
                    <div class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-5 py-7 sm:px-10 sm:py-11 lg:px-12 lg:py-12">
                        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.05] blur-3xl"></div>
                        <div class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.05] blur-3xl"></div>

                        <div class="relative z-10">
                            <div class="text-[11px] uppercase tracking-[0.24em] text-white/40">
                                Account
                            </div>

                            <div class="mt-5 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                                <div class="flex min-w-0 flex-col gap-6 sm:flex-row sm:items-center">
                                    <div class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-4 border-[#0d1119] bg-white/[0.04] shadow-xl sm:h-36 sm:w-36">
                                        <img
                                            v-if="avatarUrl"
                                            :src="avatarUrl"
                                            :alt="props.user.name"
                                            class="h-full w-full object-cover"
                                        />

                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center text-3xl font-semibold text-white"
                                        >
                                            {{ initials(props.user.name) }}
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <h1 class="truncate text-4xl font-semibold tracking-[-0.06em] text-white sm:text-6xl">
                                            {{ props.user.name }}
                                        </h1>

                                        <p class="mt-3 truncate text-sm text-white/55">
                                            {{ props.user.email }}
                                        </p>

                                        <p
                                            v-if="memberSince"
                                            class="mt-1 text-sm text-white/40"
                                        >
                                            Member since {{ memberSince }}
                                        </p>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <Link
                                        href="/profile/edit"
                                        class="inline-flex rounded-xl bg-white px-5 py-2.5 text-sm font-medium text-[#0d1119] transition hover:bg-white/90"
                                    >
                                        Edit profile
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- TRAVEL FOOTPRINT -->
                <section class="overflow-hidden rounded-[28px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]">
                    <div class="px-6 pt-7 sm:px-8">
                        <div class="flex items-end justify-between gap-6">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--vyamap-text-muted)]">
                                    Your travel world
                                </p>

                                <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                    Travel footprint
                                </h2>

                                <p class="mt-2 text-sm text-[var(--vyamap-text-muted)]">
                                    Progress by country lives in
                                    <Link href="/passport" class="text-white/80 underline-offset-4 hover:underline">Passport</Link>,
                                    and every country, city and visit in
                                    <Link href="/countries" class="text-white/80 underline-offset-4 hover:underline">Countries</Link>.
                                </p>
                            </div>

                            <div class="hidden text-right sm:block">
                                <div class="text-xl font-semibold">
                                    {{ countryTotals.countries }}
                                </div>

                                <div class="text-xs text-[var(--vyamap-text-muted)]">
                                    {{ countryTotals.countries === 1 ? 'country' : 'countries' }}
                                    <template v-if="countryTotals.territories">
                                        · {{ countryTotals.territories }}
                                        {{ countryTotals.territories === 1 ? 'territory' : 'territories' }}
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <ProfileTravelGlobe
                        :countries="props.countries"
                    />
                </section>

            </main>
        </div>
    </AppLayout>
</template>
