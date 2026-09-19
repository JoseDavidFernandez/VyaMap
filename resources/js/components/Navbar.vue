<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <header class="border-b border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">

            <!-- Brand -->
            <a
                href="/"
                class="flex items-center gap-3"
            >
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--vyamap-text)] text-xs font-semibold text-white"
                >
                    V
                </span>

                <span class="text-base font-semibold tracking-tight">
                    VyaMap
                </span>
            </a>

            <!-- Navigation -->
            <div class="flex items-center">
                <a
                    href="/"
                    class="rounded-full bg-[var(--vyamap-text)] px-4 py-2 text-sm font-medium text-white"
                >
                    Dashboard
                </a>
            </div>

            <!-- User -->
            <div class="flex items-center gap-4">
                <span
                    v-if="page.props.auth.user"
                    class="text-sm text-[var(--vyamap-text-muted)]"
                >
                    {{ page.props.auth.user.name }}
                </span>

                <button
                    type="button"
                    class="text-sm font-medium text-[var(--vyamap-text-muted)] transition-colors hover:text-[var(--vyamap-text)]"
                    @click="logout"
                >
                    Logout
                </button>
            </div>

        </nav>
    </header>
</template>