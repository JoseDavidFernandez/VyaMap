<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import Icon from '../components/ui/Icon.vue';
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
        <div class="vyamap-container vyamap-stack">
            <!-- CUENTA -->
            <section class="vyamap-card flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <div class="flex min-w-0 items-center gap-5">
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-full border-2 border-[rgba(34,211,238,0.45)] bg-[var(--vyamap-surface-raised)] sm:h-24 sm:w-24">
                        <img v-if="avatarUrl" :src="avatarUrl" :alt="props.user.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center text-2xl font-semibold">
                            {{ initials(props.user.name) }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <h1 class="vyamap-page-title truncate !text-[clamp(1.75rem,1.3rem+1.8vw,2.5rem)]">{{ props.user.name }}</h1>
                        <p class="mt-1 flex items-center gap-2 truncate text-sm text-[var(--vyamap-text-muted)]">
                            <Icon name="mail" :size="16" />
                            {{ props.user.email }}
                        </p>
                        <p v-if="memberSince" class="mt-0.5 text-sm text-[var(--vyamap-text-subtle)]">Member since {{ memberSince }}</p>
                    </div>
                </div>

                <Link href="/profile/edit" class="vyamap-button-secondary shrink-0">
                    <Icon name="edit" :size="16" />
                    Edit profile
                </Link>
            </section>

            <!-- HUELLA -->
            <section class="vyamap-card overflow-hidden">
                <div class="flex flex-wrap items-end justify-between gap-4 p-5 sm:p-7">
                    <div>
                        <h2 class="vyamap-h2">Travel footprint</h2>
                        <p class="mt-1 max-w-[60ch] text-sm text-[var(--vyamap-text-muted)]">
                            Progress by country lives in
                            <Link href="/passport" class="vyamap-link">Passport</Link>,
                            and every country, city and visit in
                            <Link href="/countries" class="vyamap-link">Countries</Link>.
                        </p>
                    </div>

                    <div class="vyamap-stat items-end text-right">
                        <span class="vyamap-stat-value">{{ countryTotals.countries }}</span>
                        <span class="vyamap-stat-label">
                            {{ countryTotals.countries === 1 ? 'country' : 'countries' }}
                            <template v-if="countryTotals.territories">
                                · {{ countryTotals.territories }} {{ countryTotals.territories === 1 ? 'territory' : 'territories' }}
                            </template>
                        </span>
                    </div>
                </div>

                <ProfileTravelGlobe :countries="props.countries" />
                <p class="border-t border-[var(--vyamap-border)] px-5 py-3 text-[0.8125rem] text-[var(--vyamap-text-subtle)] sm:px-7">
                    Drag to rotate the globe. Visited countries are raised in cyan.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
