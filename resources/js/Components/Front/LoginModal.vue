<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import PasswordSentNotice from '@/Components/Front/PasswordSentNotice.vue';
import { useI18n } from '@/i18n';
import { useAuthModal } from '@/composables/useAuthModal';

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'switch']);

const { t } = useI18n();
const page = usePage();
const form = useForm({ email: '', password: '', remember: false });

// Emailed passwords (config/registration.php): 'login' | 'forgot' (ask for a new one) | 'sent'
const view = ref('login');
const resend = useForm({ email: '' });
const sent = ref(null);

// Fill in the email from sign-up or from the emailed link (/login?email=...)
const { loginEmail } = useAuthModal();
watch(() => props.show, (open) => {
    if (!open) return;
    view.value = 'login';
    const email = loginEmail.value || new URLSearchParams(window.location.search).get('email');
    if (email) form.email = email;
    loginEmail.value = '';
}, { immediate: true });

function showForgot() {
    resend.clearErrors();
    resend.email = form.email;
    view.value = 'forgot';
}

function submitResend() {
    resend.post('/password/resend', {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = page.props.flash.passwordSent;
            form.email = resend.email;
            view.value = 'sent';
        },
    });
}

// Testing phase only: one click fills in the account and logs straight in
const demoAccounts = computed(() => usePage().props.demoAccounts ?? []);
function loginDemo(account) {
    if (form.processing) return;
    form.email = account.email;
    form.password = account.password;
    form.clearErrors();
    submit();
}

function submit() {
    form.post('/login', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
        onFinish: () => form.reset('password'),
    });
}

function close() {
    form.clearErrors();
    emit('close');
}
</script>

<template>
    <Modal :show="show" @close="close">
        <Transition name="fade" mode="out-in">
            <form v-if="view === 'forgot'" key="forgot" class="p-6 sm:p-8" @submit.prevent="submitResend">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="key" />
                </span>
                <h2 class="text-2xl font-semibold">{{ t('auth.forgotTitle') }}</h2>
                <p class="mt-1 mb-6 text-sm text-neutral-400">{{ t('auth.forgotSubtitle') }}</p>
                <FormField :label="t('auth.email')" icon="envelope" :error="resend.errors.email" v-slot="{ inputClass }">
                    <input v-model="resend.email" type="email" autocomplete="email" :class="inputClass" placeholder="name@example.com" required>
                </FormField>
                <button
                    type="submit"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60"
                    :disabled="resend.processing"
                >
                    <Fa icon="paper-plane" /> {{ t('auth.sendPassword') }}
                </button>
                <button type="button" class="mt-4 w-full text-center text-sm text-neutral-400 hover:text-gold-400" @click="view = 'login'">
                    <Fa icon="chevron-left" class="mr-1 text-xs" /> {{ t('auth.backToLogin') }}
                </button>
            </form>
            <div v-else-if="view === 'sent' && sent" key="sent" class="p-6 sm:p-8">
                <h2 class="mb-6 text-center text-2xl font-semibold">{{ t('auth.forgotSentTitle') }}</h2>
                <PasswordSentNotice :email="sent.email" :dev-password="sent.devPassword" />
                <button
                    type="button"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition hover:bg-gold-300"
                    @click="view = 'login'"
                >
                    <Fa icon="right-to-bracket" /> {{ t('auth.login') }}
                </button>
            </div>
            <form v-else key="login" class="p-6 sm:p-8" @submit.prevent="submit">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="right-to-bracket" />
                </span>
                <h2 class="text-2xl font-semibold">{{ t('auth.loginTitle') }}</h2>
                <p class="mt-1 mb-6 text-sm text-neutral-400">{{ t('auth.loginSubtitle') }}</p>

                <div class="space-y-4">
                    <FormField :label="t('auth.email')" icon="envelope" :error="form.errors.email" v-slot="{ inputClass }">
                        <input v-model="form.email" type="email" autocomplete="email" :class="inputClass" placeholder="name@example.com" required>
                    </FormField>
                    <FormField :label="t('auth.password')" icon="lock" :error="form.errors.password" v-slot="{ inputClass }">
                        <input v-model="form.password" type="password" autocomplete="current-password" :class="inputClass" required>
                    </FormField>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm text-neutral-300">
                            <input v-model="form.remember" type="checkbox" class="size-4 accent-gold-400">
                            {{ t('auth.remember') }}
                        </label>
                        <button type="button" class="text-sm text-gold-400 hover:underline" @click="showForgot">
                            {{ t('auth.forgotPassword') }}
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Fa icon="right-to-bracket" />
                    {{ t('auth.login') }}
                </button>

                <!-- Testing phase only: demo logins (config/demo.php) -->
                <div v-if="demoAccounts.length" class="mt-5 rounded-lg border border-dashed border-gold-400/40 bg-gold-400/5 p-3">
                    <p class="mb-2 flex items-center gap-2 text-xs font-semibold text-gold-400">
                        <Fa icon="flask" /> {{ t('auth.demoTitle') }}
                    </p>
                    <ul class="space-y-1 text-xs">
                        <li v-for="account in demoAccounts" :key="account.email">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded px-2 py-1 text-left transition hover:bg-white/5 disabled:opacity-60"
                                :title="t('auth.demoFill')"
                                :disabled="form.processing"
                                @click="loginDemo(account)"
                            >
                                <span class="w-24 shrink-0 text-neutral-400">{{ t(`admin.role.${account.role}`) }}</span>
                                <span class="min-w-0 flex-1 truncate text-neutral-200">{{ account.email }}</span>
                                <span class="text-neutral-500">{{ account.password }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <p class="mt-5 text-center text-sm text-neutral-400">
                    {{ t('auth.noAccount') }}
                    <button type="button" class="font-medium text-gold-400 hover:underline" @click="emit('switch')">
                        {{ t('auth.register') }}
                    </button>
                </p>
            </form>
        </Transition>
    </Modal>
</template>
