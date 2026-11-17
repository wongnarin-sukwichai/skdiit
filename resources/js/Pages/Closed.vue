<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Logo from '@/Components/Logo.vue';
import LangSwitch from '@/Components/LangSwitch.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

/*
 * The system is closed (Settings › ปิดระบบ, App\Http\Middleware\CloseSite): one page for everyone but admin, who signs
 * in from here. No site layout: nothing else of the system shows while it is closed.
 */
defineOptions({ layout: (h, page) => page });
const props = defineProps({
    message: { type: Object, required: true }, // { th, en }; empty = the default text
});

const { t, tr } = useI18n();
const site = useSite();
const conference = computed(() => site.value.conference);
const text = computed(() => tr(props.message) || t('closed.defaultMessage', { name: conference.value.shortName }));

const loginOpen = ref(false);
const form = useForm({ email: '', password: '' });
const signIn = () => form.post('/login', { onFinish: () => form.reset('password') });
const field = 'w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400';
</script>

<template>
    <Head :title="t('closed.title')" />

    <div class="dark flex min-h-screen flex-col bg-ink-950 bg-cover bg-top text-neutral-100" :style="`background-image: url('${$url('/img/bg/bg.png')}')`">
        <div class="flex justify-end p-4"><LangSwitch /></div>
        <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center gap-6 px-4 pb-16 text-center">
            <Logo />
            <p class="text-xs font-semibold tracking-[0.35em] text-gold-400 uppercase">{{ conference.shortName }}</p>
            <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ t('closed.title') }}</h1>
            <p class="max-w-xl text-base leading-relaxed whitespace-pre-line text-neutral-200">{{ text }}</p>

            <div v-if="conference.email || conference.phone" class="rounded-xl border border-gold-400/25 bg-black/40 px-5 py-3 text-sm text-neutral-300">
                <p class="mb-1 text-xs text-gold-400">{{ t('closed.contact') }}</p>
                <p v-if="conference.email"><Fa icon="envelope" class="mr-2 text-gold-400" />{{ conference.email }}</p>
                <p v-if="conference.phone"><Fa icon="phone" class="mr-2 text-gold-400" />{{ conference.phone }}</p>
            </div>

            <!-- Admin signs in here (others are turned away by AuthController::login) -->
            <div class="w-full max-w-sm">
                <button v-if="!loginOpen" type="button" class="text-xs text-neutral-500 underline-offset-2 hover:text-gold-400 hover:underline" @click="loginOpen = true">
                    {{ t('closed.adminLogin') }}
                </button>
                <Transition name="fade">
                    <form v-if="loginOpen" class="space-y-3 rounded-xl border border-white/10 bg-black/50 p-4 text-left" @submit.prevent="signIn">
                        <p class="text-sm font-medium">{{ t('closed.adminLogin') }}</p>
                        <input id="closed-email" v-model="form.email" type="email" autocomplete="email" :placeholder="t('auth.email')" :class="field" required>
                        <input id="closed-password" v-model="form.password" type="password" autocomplete="current-password" :placeholder="t('auth.password')" :class="field" required>
                        <p v-if="form.errors.email" class="text-xs text-red-400">{{ t(form.errors.email) }}</p>
                        <button type="submit" class="w-full rounded-lg bg-gold-400 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing">
                            <Fa icon="right-to-bracket" class="mr-1.5" />{{ t('auth.login') }}
                        </button>
                    </form>
                </Transition>
            </div>
        </main>
    </div>
</template>
