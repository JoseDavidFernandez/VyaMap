<script setup lang="ts">
import { router, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const mobileMenuOpen = ref(false);

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};

const user = computed(() => page.props.auth?.user);

const avatar = computed(() => {
    return user.value?.avatar ?? null;
});

const userName = computed(() => {
    return user.value?.name ?? 'Profile';
});

const logout = () => {
    router.post('/logout');
    closeMobileMenu();
};
</script>

<template>
    <!-- Mobile navbar -->
    <header class="fixed inset-x-0 top-0 z-50 border-b border-[var(--vyamap-border)] bg-[var(--vyamap-background)] lg:hidden">
        <div class="flex h-16 items-center justify-between px-4">
            <Link
                href="/"
                class="flex items-center gap-3"
                @click="closeMobileMenu"
            >
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--vyamap-text)] text-sm font-semibold text-[var(--vyamap-background)]"
                >
                    V
                </span>

                <span class="text-base font-semibold tracking-tight text-[var(--vyamap-text)]">
                    VyaMap
                </span>
            </Link>

            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-xl text-[var(--vyamap-text-muted)] transition-colors hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]"
                :aria-expanded="mobileMenuOpen"
                aria-label="Toggle navigation menu"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <svg
                    v-if="!mobileMenuOpen"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-6 w-6"
                >
                    <path d="M4 7h16" />
                    <path d="M4 12h16" />
                    <path d="M4 17h16" />
                </svg>

                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-6 w-6"
                >
                    <path d="M6 6l12 12" />
                    <path d="M18 6 6 18" />
                </svg>
            </button>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="mobileMenuOpen"
                class="border-t border-[var(--vyamap-border)] bg-[var(--vyamap-background)] px-3 pb-4 pt-3 shadow-2xl"
            >
                <nav class="space-y-1.5">
                    <Link
                        href="/"
                        class="flex h-11 items-center rounded-xl px-3 text-sm font-medium transition-colors"
                        :class="page.url === '/' ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]' : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'"
                        @click="closeMobileMenu"
                    >
                        Home
                    </Link>

                    <Link
                        href="/trips"
                        class="flex h-11 items-center rounded-xl px-3 text-sm font-medium transition-colors"
                        :class="page.url.startsWith('/trips') ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]' : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'"
                        @click="closeMobileMenu"
                    >
                        Trips
                    </Link>

                    <Link
                        href="/countries"
                        class="flex h-11 items-center rounded-xl px-3 text-sm font-medium transition-colors"
                        :class="page.url.startsWith('/countries') ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]' : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'"
                        @click="closeMobileMenu"
                    >
                        Countries
                    </Link>

                    <Link
                        href="/photos"
                        class="flex h-11 items-center rounded-xl px-3 text-sm font-medium transition-colors"
                        :class="page.url.startsWith('/photos') ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]' : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'"
                        @click="closeMobileMenu"
                    >
                        Photos
                    </Link>

                    <Link
                        href="/passport"
                        class="flex h-11 items-center rounded-xl px-3 text-sm font-medium transition-colors"
                        :class="page.url.startsWith('/passport') ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]' : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'"
                        @click="closeMobileMenu"
                    >
                        Passport
                    </Link>

                    <Link
                        href="/flight-history"
                        class="flex h-11 items-center rounded-xl px-3 text-sm font-medium transition-colors"
                        :class="page.url.startsWith('/flight-history') ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]' : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'"
                        @click="closeMobileMenu"
                    >
                        Flight History
                    </Link>

                    <Link
                        href="/profile"
                        class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors"
                        :class="page.url.startsWith('/profile') ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]' : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'"
                        @click="closeMobileMenu"
                    >
                        <span class="h-7 w-7 shrink-0 overflow-hidden rounded-full bg-[var(--vyamap-surface-strong)]">
                            <img
                                v-if="avatar"
                                :src="`/storage/${avatar}`"
                                :alt="userName"
                                class="h-full w-full object-cover"
                            />
                            <span
                                v-else
                                class="flex h-full w-full items-center justify-center text-[10px] font-semibold text-[var(--vyamap-text)]"
                            >
                                {{ userName.charAt(0).toUpperCase() }}
                            </span>
                        </span>
                        Profile
                    </Link>

                    <div class="pt-2">
                        <Link
                            href="/trips/create"
                            class="flex h-11 items-center justify-center rounded-xl bg-[var(--vyamap-text)] px-3 text-sm font-semibold text-[var(--vyamap-background)] transition-opacity hover:opacity-90"
                            @click="closeMobileMenu"
                        >
                            + New trip
                        </Link>
                    </div>

                    <div class="border-t border-[var(--vyamap-border)] pt-2">
                        <button
                            type="button"
                            class="flex h-11 w-full items-center rounded-xl px-3 text-sm font-medium text-[var(--vyamap-text-muted)] transition-colors hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]"
                            @click="logout"
                        >
                            Logout
                        </button>
                    </div>
                </nav>
            </div>
        </Transition>
    </header>

    <aside
        class="group fixed inset-y-0 left-0 z-50 hidden w-[72px] border-r border-[var(--vyamap-border)] bg-[var(--vyamap-background)] transition-[width] duration-300 ease-out hover:w-[240px] lg:block"
    >
        <div class="flex h-full flex-col">

            <!-- Brand -->
            <div class="flex h-20 items-center px-3">
                <Link
                    href="/"
                    class="flex h-12 w-full items-center overflow-hidden rounded-xl transition-colors hover:bg-[var(--vyamap-surface)]"
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--vyamap-text)] text-sm font-semibold text-[var(--vyamap-background)]"
                    >
                        V
                    </span>

                    <span
                        class="ml-4 whitespace-nowrap text-base font-semibold tracking-tight text-[var(--vyamap-text)] opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                    >
                        VyaMap
                    </span>
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex flex-1 flex-col px-3 pt-6">

                <!-- Main navigation -->
                <div class="space-y-2">

                    <!-- Home -->
                    <Link
                        href="/"
                        class="flex h-12 w-full items-center rounded-xl px-3 transition-colors"
                        :class="
                            page.url === '/'
                                ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]'
                                : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'
                        "
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6 shrink-0"
                        >
                            <path d="M3 10.5 12 3l9 7.5" />
                            <path d="M5.5 9.5V21h13V9.5" />
                            <path d="M9.5 21v-6h5v6" />
                        </svg>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Home
                        </span>
                    </Link>

                    <!-- Trips -->
                    <Link
                        href="/trips"
                        class="flex h-12 w-full items-center rounded-xl px-3 transition-colors"
                        :class="
                            page.url.startsWith('/trips')
                                ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]'
                                : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'
                        "
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6 shrink-0"
                        >
                            <path d="M3 6.5 12 3l9 3.5-9 3.5L3 6.5Z" />
                            <path d="M3 6.5V17.5L12 21l9-3.5V6.5" />
                            <path d="M12 10v11" />
                        </svg>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Trips
                        </span>
                    </Link>

                    <!-- Countries -->
                    <Link
                        href="/countries"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/45 transition hover:bg-white/[0.05] hover:text-white"
                    >
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center text-base"
                        >
                            ◉
                        </span>

                        <span
                            class="whitespace-nowrap opacity-0 transition duration-200 group-hover:opacity-100"
                        >
                            Countries
                        </span>
                    </Link>

                    <!-- Photos -->
                    <Link
                        href="/photos"
                        class="flex h-12 w-full items-center rounded-xl px-3 transition-colors"
                        :class="
                            page.url.startsWith('/photos')
                                ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]'
                                : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'
                        "
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6 shrink-0"
                        >
                            <rect
                                x="3"
                                y="4"
                                width="18"
                                height="16"
                                rx="2"
                            />

                            <circle
                                cx="8.5"
                                cy="9"
                                r="1.5"
                            />

                            <path d="m3 17 5-5 4 4 2.5-2.5L21 18" />
                        </svg>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Photos
                        </span>
                    </Link>

                    <!-- Passport -->
                    <Link
                        href="/passport"
                        class="flex h-12 w-full items-center rounded-xl px-3 transition-colors"
                        :class="
                            page.url.startsWith('/passport')
                                ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]'
                                : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'
                        "
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6 shrink-0"
                        >
                            <rect
                                x="4"
                                y="3"
                                width="16"
                                height="18"
                                rx="2"
                            />

                            <circle
                                cx="12"
                                cy="10"
                                r="3"
                            />

                            <path d="M8 17h8" />
                        </svg>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Passport
                        </span>
                    </Link>

                    <!-- Flight History -->
                    <Link
                        href="/flight-history"
                        class="flex h-12 w-full items-center rounded-xl px-3 transition-colors"
                        :class="
                            page.url.startsWith('/flight-history')
                                ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]'
                                : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'
                        "
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6 shrink-0"
                        >
                            <path d="M2.5 16.5 21 8" />
                            <path d="M21 8 14 3.5" />
                            <path d="M21 8 17 14" />
                            <path d="M14 3.5 11.5 3" />
                            <path d="M17 14 14 13" />
                            <path d="M7.5 14.5 5 20" />
                            <path d="M5 20 2.5 19" />
                        </svg>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Flight History
                        </span>
                    </Link>

                    <!-- Profile -->
                    <Link
                        href="/profile"
                        class="flex h-12 w-full items-center rounded-xl px-3 transition-colors"
                        :class="
                            page.url.startsWith('/profile')
                                ? 'bg-[var(--vyamap-surface-strong)] text-[var(--vyamap-text)]'
                                : 'text-[var(--vyamap-text-muted)] hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]'
                        "
                    >
                        <span
                            class="h-7 w-7 shrink-0 overflow-hidden rounded-full bg-[var(--vyamap-surface-strong)]"
                        >
                            <img
                                v-if="avatar"
                                :src="`/storage/${avatar}`"
                                :alt="userName"
                                class="h-full w-full object-cover"
                            />

                            <span
                                v-else
                                class="flex h-full w-full items-center justify-center text-[10px] font-semibold text-[var(--vyamap-text)]"
                            >
                                {{
                                    userName
                                        .charAt(0)
                                        .toUpperCase()
                                }}
                            </span>
                        </span>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Profile
                        </span>
                    </Link>

                </div>

                <!-- New trip -->
                <div class="mt-6">
                    <Link
                        href="/trips/create"
                        class="flex h-12 w-full items-center rounded-xl bg-[var(--vyamap-text)] px-3 text-[var(--vyamap-background)] transition-opacity hover:opacity-90"
                    >
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-5 w-5"
                            >
                                <path d="M12 5v14" />
                                <path d="M5 12h14" />
                            </svg>
                        </span>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-semibold opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            New trip
                        </span>
                    </Link>
                </div>

                <!-- Logout -->
                <div class="mt-auto pb-4">
                    <button
                        type="button"
                        class="flex h-12 w-full items-center rounded-xl px-3 text-[var(--vyamap-text-muted)] transition-colors hover:bg-[var(--vyamap-surface)] hover:text-[var(--vyamap-text)]"
                        @click="logout"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6 shrink-0"
                        >
                            <path d="M10 17l5-5-5-5" />
                            <path d="M15 12H3" />
                            <path d="M21 19V5a2 2 0 0 0-2-2h-6" />
                            <path d="M13 21h6a2 2 0 0 0 2-2" />
                        </svg>

                        <span
                            class="ml-4 whitespace-nowrap text-sm font-medium opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Logout
                        </span>
                    </button>
                </div>

            </nav>
        </div>
    </aside>
</template>