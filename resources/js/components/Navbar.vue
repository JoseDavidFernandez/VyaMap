<script setup lang="ts">
import { router, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Icon, { type IconName } from './ui/Icon.vue';

/**
 * Navegación principal.
 * - ≥1280 px: barra lateral con etiquetas.
 * - 1024–1279 px: rail de iconos (cada enlace conserva su nombre accesible).
 * - <1024 px: barra superior (marca, nuevo viaje, perfil) y barra inferior con
 *   los cuatro destinos principales más «More», que abre el resto.
 */
interface NavItem {
    label: string;
    href: string;
    icon: IconName;
    match: (url: string) => boolean;
}

const page = usePage();
const moreOpen = ref(false);

const path = computed(() => page.url.split('?')[0]);

// Photos solo aparece si la cuenta ya tiene fotos: no se pueden subir nuevas.
const items = computed<NavItem[]>(() => [
    { label: 'Home', href: '/', icon: 'home', match: (url) => url === '/' },
    { label: 'Trips', href: '/trips', icon: 'trips', match: (url) => url.startsWith('/trips') },
    { label: 'Flights', href: '/flight-history', icon: 'flight', match: (url) => url.startsWith('/flight-history') },
    { label: 'Passport', href: '/passport', icon: 'passport', match: (url) => url.startsWith('/passport') },
    { label: 'Countries', href: '/countries', icon: 'globe', match: (url) => url.startsWith('/countries') },
    ...(page.props.features?.photos
        ? [{ label: 'Photos', href: '/photos', icon: 'photo' as IconName, match: (url: string) => url.startsWith('/photos') }]
        : []),
]);

const primaryMobile = computed(() => items.value.slice(0, 4));
const secondaryMobile = computed(() => items.value.slice(4));

const isActive = (item: NavItem) => item.match(path.value);
const moreActive = computed(() => secondaryMobile.value.some(isActive) || path.value.startsWith('/profile'));

const user = computed(() => page.props.auth?.user);
const avatar = computed(() => user.value?.avatar ?? null);
const userName = computed(() => user.value?.name ?? 'Profile');
const initial = computed(() => userName.value.charAt(0).toUpperCase());

const closeMore = () => {
    moreOpen.value = false;
};

const logout = () => {
    closeMore();
    router.post('/logout');
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeMore();
    }
};

watch(moreOpen, (open) => {
    if (open) {
        window.addEventListener('keydown', onKeydown);
    } else {
        window.removeEventListener('keydown', onKeydown);
    }
});

watch(path, closeMore);

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <!-- ESCRITORIO: rail (lg) y barra lateral con etiquetas (xl) -->
    <aside
        class="vyamap-glass fixed inset-y-0 left-0 z-50 hidden w-[72px] !rounded-none !border-y-0 !border-l-0 lg:flex xl:w-[var(--vyamap-nav-width)]"
        aria-label="Main navigation"
    >
        <div class="flex h-full w-full flex-col px-3 py-5">
            <Link
                href="/"
                class="flex h-11 items-center gap-3 rounded-[var(--vyamap-radius-sm)] px-1.5"
                aria-label="VyaMap home"
            >
                <span class="vyamap-brand-mark">
                    V
                </span>
                <span class="hidden text-[1.0625rem] font-semibold tracking-[-0.02em] xl:inline">
                    VyaMap
                </span>
            </Link>

            <Link
                href="/trips/create"
                class="vyamap-button mt-6 !h-11 !px-0 xl:!px-4"
                aria-label="New trip"
            >
                <Icon name="plus" :size="18" />
                <span class="hidden xl:inline">New trip</span>
            </Link>

            <nav class="mt-6 flex flex-col gap-1">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="vyamap-nav-link"
                    :class="{ 'is-active': isActive(item) }"
                    :aria-current="isActive(item) ? 'page' : undefined"
                    :aria-label="item.label"
                    :title="item.label"
                >
                    <Icon :name="item.icon" :size="20" />
                    <span class="hidden xl:inline">{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-auto flex flex-col gap-1 border-t border-[var(--vyamap-border)] pt-4">
                <Link
                    href="/profile"
                    class="vyamap-nav-link"
                    :class="{ 'is-active': path.startsWith('/profile') }"
                    :aria-current="path.startsWith('/profile') ? 'page' : undefined"
                    :aria-label="`Profile (${userName})`"
                    :title="userName"
                >
                    <span class="vyamap-avatar">
                        <img v-if="avatar" :src="`/storage/${avatar}`" alt="" />
                        <span v-else>{{ initial }}</span>
                    </span>
                    <span class="hidden min-w-0 truncate xl:inline">{{ userName }}</span>
                </Link>

                <button
                    type="button"
                    class="vyamap-nav-link"
                    aria-label="Log out"
                    title="Log out"
                    @click="logout"
                >
                    <Icon name="logout" :size="20" />
                    <span class="hidden xl:inline">Log out</span>
                </button>
            </div>
        </div>
    </aside>

    <!-- MÓVIL Y TABLETA: barra superior -->
    <header
        class="vyamap-glass fixed inset-x-0 top-0 z-50 !rounded-none !border-x-0 !border-t-0 lg:hidden"
        style="padding-top: env(safe-area-inset-top)"
    >
        <div class="flex h-14 items-center justify-between gap-3 px-4">
            <Link href="/" class="flex items-center gap-2.5" aria-label="VyaMap home">
                <span class="vyamap-brand-mark !h-8 !w-8 !text-sm">V</span>
                <span class="text-base font-semibold tracking-[-0.02em]">VyaMap</span>
            </Link>

            <div class="flex items-center gap-2">
                <Link href="/trips/create" class="vyamap-button vyamap-button-sm" aria-label="New trip">
                    <Icon name="plus" :size="16" />
                    <span>New trip</span>
                </Link>

                <Link
                    href="/profile"
                    class="vyamap-avatar !h-9 !w-9"
                    :aria-label="`Profile (${userName})`"
                >
                    <img v-if="avatar" :src="`/storage/${avatar}`" alt="" />
                    <span v-else>{{ initial }}</span>
                </Link>
            </div>
        </div>
    </header>

    <!-- MÓVIL Y TABLETA: barra inferior -->
    <nav
        class="vyamap-glass fixed inset-x-0 bottom-0 z-50 !rounded-none !border-x-0 !border-b-0 lg:hidden"
        style="padding-bottom: env(safe-area-inset-bottom)"
        aria-label="Main navigation"
    >
        <div class="mx-auto grid h-16 max-w-xl grid-cols-5">
            <Link
                v-for="item in primaryMobile"
                :key="item.href"
                :href="item.href"
                class="vyamap-tab"
                :class="{ 'is-active': isActive(item) }"
                :aria-current="isActive(item) ? 'page' : undefined"
            >
                <Icon :name="item.icon" :size="22" />
                <span>{{ item.label }}</span>
            </Link>

            <button
                type="button"
                class="vyamap-tab"
                :class="{ 'is-active': moreActive || moreOpen }"
                :aria-expanded="moreOpen"
                aria-controls="vyamap-more-sheet"
                @click="moreOpen = !moreOpen"
            >
                <Icon :name="moreOpen ? 'close' : 'more'" :size="22" />
                <span>More</span>
            </button>
        </div>
    </nav>

    <!-- Hoja «More» -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0 translate-y-3"
    >
        <div v-if="moreOpen" class="fixed inset-0 z-40 lg:hidden">
            <button
                type="button"
                class="absolute inset-0 bg-[rgba(4,7,14,0.6)]"
                aria-label="Close menu"
                @click="closeMore"
            />

            <div
                id="vyamap-more-sheet"
                class="vyamap-glass absolute inset-x-3 rounded-[var(--vyamap-radius-xl)] p-2"
                style="bottom: calc(4.5rem + env(safe-area-inset-bottom))"
                role="dialog"
                aria-label="More navigation"
            >
                <Link
                    v-for="item in secondaryMobile"
                    :key="item.href"
                    :href="item.href"
                    class="vyamap-nav-link !h-12"
                    :class="{ 'is-active': isActive(item) }"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <Icon :name="item.icon" :size="20" />
                    <span>{{ item.label }}</span>
                </Link>

                <Link
                    href="/profile"
                    class="vyamap-nav-link !h-12"
                    :class="{ 'is-active': path.startsWith('/profile') }"
                >
                    <Icon name="user" :size="20" />
                    <span>Profile</span>
                </Link>

                <div class="my-1 h-px bg-[var(--vyamap-border)]" />

                <button type="button" class="vyamap-nav-link !h-12 w-full" @click="logout">
                    <Icon name="logout" :size="20" />
                    <span>Log out</span>
                </button>
            </div>
        </div>
    </Transition>
</template>

<style>
.vyamap-brand-mark {
    display: inline-flex;
    height: 2.25rem;
    width: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    background:
        linear-gradient(135deg, rgba(34, 211, 238, 0.95), rgba(124, 92, 255, 0.95));
    color: #041019;
    font-size: 1rem;
    font-weight: 750;
    letter-spacing: -0.04em;
    box-shadow: 0 6px 16px -6px rgba(34, 211, 238, 0.6);
}

.vyamap-avatar {
    display: inline-flex;
    height: 1.75rem;
    width: 1.75rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--vyamap-border-strong);
    border-radius: 9999px;
    background: var(--vyamap-surface-raised);
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--vyamap-text);
}

.vyamap-avatar img {
    height: 100%;
    width: 100%;
    object-fit: cover;
}

.vyamap-nav-link {
    position: relative;
    display: flex;
    height: 2.75rem;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    border-radius: var(--vyamap-radius-sm);
    padding: 0 0.75rem;
    font-size: 0.875rem;
    font-weight: 540;
    color: var(--vyamap-text-muted);
    transition: background-color 0.15s var(--vyamap-ease), color 0.15s var(--vyamap-ease);
}

@media (min-width: 1280px) {
    aside .vyamap-nav-link {
        justify-content: flex-start;
    }
}

@media (max-width: 1023px) {
    .vyamap-nav-link {
        justify-content: flex-start;
    }
}

.vyamap-nav-link:hover {
    background: var(--vyamap-surface-strong);
    color: var(--vyamap-text);
}

.vyamap-nav-link.is-active {
    background: rgba(34, 211, 238, 0.1);
    color: var(--vyamap-text);
}

.vyamap-nav-link.is-active::before {
    content: '';
    position: absolute;
    left: -0.75rem;
    top: 0.5rem;
    bottom: 0.5rem;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: linear-gradient(to bottom, #22D3EE, #A78BFA);
}

.vyamap-nav-link.is-active svg {
    color: var(--vyamap-cyan-soft);
}

#vyamap-more-sheet .vyamap-nav-link.is-active::before {
    left: 0;
}

.vyamap-tab {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    font-size: 0.6875rem;
    font-weight: 560;
    color: var(--vyamap-text-subtle);
    transition: color 0.15s var(--vyamap-ease);
}

.vyamap-tab:hover {
    color: var(--vyamap-text);
}

.vyamap-tab.is-active {
    color: var(--vyamap-cyan-soft);
}

.vyamap-tab.is-active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 30%;
    right: 30%;
    height: 2px;
    border-radius: 0 0 2px 2px;
    background: var(--vyamap-route);
}
</style>
