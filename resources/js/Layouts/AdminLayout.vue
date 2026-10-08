<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Logo from '@/Components/Logo.vue';
import LangSwitch from '@/Components/LangSwitch.vue';
import FlashToast from '@/Components/FlashToast.vue';
import { useI18n } from '@/i18n';
import { useTheme } from '@/composables/useTheme';
import { useModules } from '@/composables/useModules';

const page = usePage();
const { t } = useI18n();
const { isDark, toggleTheme } = useTheme();

const user = computed(() => page.props.auth.user);
const sidebarOpen = ref(false);
const userMenuOpen = ref(false);

// Sidebar shows only the modules this user's role can open (config/modules.php)
const { modules: menu } = useModules();

const path = computed(() => page.url.split('?')[0]);
const isActive = (href) => (href === '/admin' ? path.value === '/admin' : path.value.startsWith(href));

watch(() => page.url, () => {
    sidebarOpen.value = false;
    userMenuOpen.value = false;
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-neutral-100 text-neutral-800 transition-colors duration-300 dark:bg-ink-950 dark:text-neutral-100">
        <!-- Headbar: black in both themes, as in the design -->
        <header class="dark sticky top-0 z-40 flex h-20 items-center gap-3 border-b border-gold-400/20 bg-ink-950 px-4 text-neutral-100 sm:px-6">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="gold-streaks absolute inset-y-0 right-0 w-2/3 opacity-30" />
            </div>

            <button
                class="relative grid size-10 place-items-center rounded-lg border border-white/15 lg:hidden"
                :aria-label="t('nav.menu')"
                @click="sidebarOpen = !sidebarOpen"
            >
                <Fa :icon="sidebarOpen ? 'xmark' : 'bars'" />
            </button>
            <Link href="/admin" class="relative">
                <Logo tagline class="hidden sm:inline-flex" />
                <Logo class="sm:hidden" />
            </Link>

            <div class="relative ml-auto flex items-center gap-2 sm:gap-3">
                <Link
                    href="/"
                    class="flex items-center gap-2 rounded-lg border border-gold-400/60 px-3 py-2 text-sm text-gold-300 transition hover:bg-gold-400 hover:text-black"
                    :title="t('admin.backHome')"
                >
                    <Fa icon="house" />
                    <span class="hidden md:inline">{{ t('admin.backHome') }}</span>
                </Link>

                <LangSwitch class="hidden sm:inline-flex" />

                <button
                    class="grid size-10 place-items-center rounded-lg border border-white/15 transition hover:border-gold-400 hover:text-gold-400"
                    :title="isDark ? t('admin.themeLight') : t('admin.themeDark')"
                    :aria-label="isDark ? t('admin.themeLight') : t('admin.themeDark')"
                    @click="toggleTheme"
                >
                    <Transition name="fade" mode="out-in">
                        <Fa :key="isDark ? 'sun' : 'moon'" :icon="isDark ? 'sun' : 'moon'" />
                    </Transition>
                </button>

                <button class="relative hidden size-10 place-items-center text-xl sm:grid" :aria-label="t('admin.notifications')">
                    <Fa icon="bell" />
                    <span class="absolute top-1.5 right-1.5 size-2.5 rounded-full bg-gold-400" />
                </button>

                <span class="hidden h-10 w-px bg-gold-400/40 sm:block" />

                <div class="relative">
                    <button class="flex items-center gap-3" @click="userMenuOpen = !userMenuOpen">
                        <span class="grid size-11 place-items-center rounded-full border-2 border-gold-400 text-gold-400">
                            <Fa icon="user" />
                        </span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block max-w-40 truncate font-medium">{{ user.name }}</span>
                            <span class="text-sm text-neutral-400">{{ t(`admin.role.${user.accessRole}`) }}</span>
                        </span>
                        <Fa icon="chevron-down" class="hidden text-xs text-neutral-400 md:block" />
                    </button>
                    <Transition name="fade">
                        <div v-if="userMenuOpen" class="absolute right-0 mt-3 w-56 overflow-hidden rounded-xl border border-neutral-200 bg-white py-1 text-neutral-800 shadow-xl dark:border-white/10 dark:bg-ink-900 dark:text-neutral-100">
                            <p class="truncate border-b border-neutral-200 px-4 py-2.5 text-xs text-neutral-500 dark:border-white/10">{{ user.email }}</p>
                            <LangSwitch class="mx-4 my-2 sm:hidden" />
                            <button class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-white/5" @click="logout">
                                <Fa icon="arrow-right-from-bracket" class="text-gold-500" /> {{ t('auth.logout') }}
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>
        </header>

        <!-- Mobile sidebar backdrop -->
        <Transition name="fade">
            <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/50 lg:hidden" @click="sidebarOpen = false" />
        </Transition>

        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed top-20 bottom-0 left-0 z-30 flex w-64 flex-col border-r border-neutral-200 bg-white transition-transform duration-300 dark:border-white/10 dark:bg-ink-900"
        >
            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                <Link
                    v-for="item in menu"
                    :key="item.key"
                    :href="item.href"
                    :class="isActive(item.href)
                        ? 'bg-linear-to-r from-gold-400 to-gold-300 font-semibold text-black shadow-[0_0_20px_rgba(255,204,41,.25)]'
                        : 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-white/5'"
                    class="flex items-center gap-3 rounded-lg px-4 py-3 transition"
                >
                    <Fa :icon="item.icon" class="w-5 text-lg" :class="isActive(item.href) ? '' : 'text-neutral-500 dark:text-neutral-400'" />
                    {{ t(`admin.menu.${item.key}`) }}
                </Link>

                <hr class="mx-3 my-4 border-neutral-200 dark:border-white/10">

                <Link href="/" class="flex items-center gap-3 rounded-lg px-4 py-3 text-neutral-700 transition hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-white/5">
                    <Fa icon="arrow-up-right-from-square" class="w-5 text-lg text-neutral-500 dark:text-neutral-400" />
                    {{ t('admin.menu.website') }}
                </Link>
            </nav>
            <p class="border-t border-neutral-200 p-5 text-xs text-neutral-500 dark:border-white/10 dark:text-neutral-400">
                {{ t('brand.creditShort') }}
            </p>
        </aside>

        <main class="p-4 sm:p-6 lg:ml-64">
            <Transition name="fade" mode="out-in">
                <div :key="page.url">
                    <slot />
                </div>
            </Transition>
        </main>

        <FlashToast />
    </div>
</template>
