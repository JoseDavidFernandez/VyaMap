<script setup lang="ts">
import { router, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const user = computed(() => page.props.auth?.user);

const avatar = computed(() => {
    console.log('Navbar avatar:', user.value?.avatar);
    return user.value?.avatar ?? null;
});
const userName = computed(() => user.value?.name ?? 'Profile');

const logout = () => {
    router.post('/logout');
};
</script>

<template>
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
                            page.url.startsWith(
                                '/flight-history',
                            )
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