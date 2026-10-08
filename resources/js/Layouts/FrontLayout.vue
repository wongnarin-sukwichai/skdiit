<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Logo from '@/Components/Logo.vue';
import LangSwitch from '@/Components/LangSwitch.vue';
import FlashToast from '@/Components/FlashToast.vue';
import LoginModal from '@/Components/Front/LoginModal.vue';
import RegisterModal from '@/Components/Front/RegisterModal.vue';
import { useI18n } from '@/i18n';
import { useAuthModal } from '@/composables/useAuthModal';

const page = usePage();
const { t } = useI18n();

const user = computed(() => page.props.auth.user);
const { authModal: modal } = useAuthModal(); // 'login' | 'register' | null
const mobileOpen = ref(false);
const userMenuOpen = ref(false);

const navItems = [
    { href: '/', key: 'home' },
    { href: '/about', key: 'about' },
    { href: '/schedule', key: 'schedule' },
    { href: '/submission', key: 'submission' },
    { href: '/important', key: 'important' },
    { href: '/contact', key: 'contact' },
];

const isActive = (href) => (href === '/' ? page.url.split('?')[0] === '/' : page.url.startsWith(href));

// Close menus whenever the page changes
watch(() => page.url, () => {
    mobileOpen.value = false;
    userMenuOpen.value = false;
});

// /login and /register (also where protected pages send guests) open their modal on top of the home page
watch(() => page.props.authModal, (value) => {
    if (value && !user.value) modal.value = value;
}, { immediate: true });

// On /login or /register, keep the URL in step with the modal; closing it goes back to /
function setModal(value) {
    modal.value = value;
    if (['/login', '/register'].includes(window.location.pathname)) {
        router.visit(value ? `/${value}` : '/', { replace: true, preserveState: true, preserveScroll: true });
    }
}
function logout() {
    router.post('/logout');
}
</script>

<template>
    <!-- The public site is always dark; .dark scopes shared components to their dark styles -->
    <div class="front-bg dark relative isolate flex min-h-screen flex-col text-neutral-100">
        <!-- Page background image: adjust its strength with opacity-* (e.g. opacity-60) -->
        <!-- Mobile/tablet: dimmed band at the top that fades into black, so the hero text stays readable -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-130 bg-cover bg-top-right bg-no-repeat opacity-35 mask-b-from-40% lg:hidden"
            style="background-image: url('/img/bg/bg.png')"
            aria-hidden="true"
        />
        <!-- Desktop: full-page background -->
        <div
            class="pointer-events-none fixed inset-0 -z-10 hidden bg-cover bg-top-right bg-no-repeat opacity-100 lg:block"
            style="background-image: url('/img/bg/bg.png')"
            aria-hidden="true"
        />
        <header class="sticky top-0 z-40 border-b border-white/10 bg-ink-950/85 backdrop-blur">
            <div class="mx-auto flex max-w-[1600px] items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <Link href="/" class="shrink-0" aria-label="SKDIIT home">
                    <Logo />
                </Link>

                <nav class="ml-auto hidden items-center gap-1 lg:flex">
                    <Link
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        :class="isActive(item.href) ? 'text-gold-400' : 'text-neutral-200 hover:text-gold-300'"
                        class="relative px-3 py-2 text-[0.95rem] transition"
                    >
                        {{ t(`nav.${item.key}`) }}
                        <span
                            :class="isActive(item.href) ? 'opacity-100' : 'opacity-0'"
                            class="absolute inset-x-2 -bottom-3.25 h-0.5 bg-gold-400 shadow-[0_0_10px_rgba(255,204,41,.9)] transition-opacity"
                        />
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2 lg:ml-4">
                    <LangSwitch class="hidden sm:inline-flex" />

                    <Transition name="fade" mode="out-in">
                        <div v-if="!user" key="guest" class="hidden items-center gap-2 sm:flex">
                            <button
                                class="flex items-center gap-2 rounded-lg border border-gold-400/70 px-4 py-2 text-sm text-white transition hover:bg-gold-400/10"
                                @click="modal = 'login'"
                            >
                                <Fa :icon="['far', 'user']" />
                                {{ t('auth.login') }}
                            </button>
                            <button
                                class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-black transition hover:bg-gold-300"
                                @click="modal = 'register'"
                            >
                                <Fa icon="user-plus" />
                                {{ t('auth.register') }}
                            </button>
                        </div>

                        <div v-else key="user" class="relative hidden sm:block">
                            <button
                                class="flex items-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-sm transition hover:border-gold-400/60"
                                @click="userMenuOpen = !userMenuOpen"
                            >
                                <span class="grid size-7 place-items-center rounded-full bg-gold-400/15 text-gold-400"><Fa icon="user" class="text-xs" /></span>
                                <span class="max-w-36 truncate">{{ user.name }}</span>
                                <Fa icon="chevron-down" class="text-xs text-neutral-400" />
                            </button>
                            <Transition name="fade">
                                <div v-if="userMenuOpen" class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-white/10 bg-ink-900 py-1 shadow-xl">
                                    <Link v-if="page.props.auth.modules.length" href="/admin" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-white/5">
                                        <Fa icon="gauge" class="text-gold-400" /> {{ t('auth.backend') }}
                                    </Link>
                                    <button class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-white/5" @click="logout">
                                        <Fa icon="arrow-right-from-bracket" class="text-gold-400" /> {{ t('auth.logout') }}
                                    </button>
                                </div>
                            </Transition>
                        </div>
                    </Transition>

                    <button
                        class="grid size-10 place-items-center rounded-lg border border-white/15 lg:hidden"
                        :aria-label="t('nav.menu')"
                        :aria-expanded="mobileOpen"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Fa :icon="mobileOpen ? 'xmark' : 'bars'" />
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <Transition name="fade">
                <div v-if="mobileOpen" class="border-t border-white/10 px-4 pb-4 lg:hidden">
                    <nav class="flex flex-col py-2">
                        <Link
                            v-for="item in navItems"
                            :key="item.key"
                            :href="item.href"
                            :class="isActive(item.href) ? 'text-gold-400' : 'text-neutral-200'"
                            class="py-2.5"
                        >
                            {{ t(`nav.${item.key}`) }}
                        </Link>
                    </nav>
                    <div class="flex flex-wrap items-center gap-2 border-t border-white/10 pt-4">
                        <LangSwitch />
                        <template v-if="!user">
                            <button class="rounded-lg border border-gold-400/70 px-4 py-2 text-sm" @click="modal = 'login'">{{ t('auth.login') }}</button>
                            <button class="rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-black" @click="modal = 'register'">{{ t('auth.register') }}</button>
                        </template>
                        <template v-else>
                            <Link v-if="page.props.auth.modules.length" href="/admin" class="rounded-lg border border-white/15 px-4 py-2 text-sm">{{ t('auth.backend') }}</Link>
                            <button class="rounded-lg border border-white/15 px-4 py-2 text-sm" @click="logout">{{ t('auth.logout') }}</button>
                        </template>
                    </div>
                </div>
            </Transition>
        </header>

        <main class="flex-1">
            <Transition name="fade" mode="out-in">
                <div :key="page.component">
                    <slot />
                </div>
            </Transition>
        </main>

        <footer class="mx-auto flex w-full max-w-[1600px] items-center gap-4 px-4 py-6 text-sm text-neutral-400 sm:px-6 lg:px-8">
            <span class="glow-line flex-1" />
            <span class="text-center">{{ t('brand.credit') }}</span>
            <span class="glow-line flex-1" />
        </footer>

        <LoginModal :show="modal === 'login'" @close="setModal(null)" @switch="setModal('register')" />
        <RegisterModal :show="modal === 'register'" @close="setModal(null)" @switch="setModal('login')" />
        <FlashToast />
    </div>
</template>
