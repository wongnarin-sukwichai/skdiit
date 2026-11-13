<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import AffiliationField from '@/Components/AffiliationField.vue';
import MealFields from '@/Components/MealFields.vue';
import PasswordSentNotice from '@/Components/Front/PasswordSentNotice.vue';
import { useI18n } from '@/i18n';
import { useAuthModal } from '@/composables/useAuthModal';
import { OTHER_AFFILIATION } from '@/composables/useAffiliations';
import { mealFormFields, mealPayload } from '@/composables/useMealOptions';

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'switch']);

const { t, formatDate } = useI18n();
const page = usePage();
// Settings › กำหนดการ, row "registration": outside its dates the form is replaced by a notice
const registration = computed(() => page.props.registration ?? { state: 'open' });
const longDate = (d) => (d ? formatDate(d, { day: 'numeric', month: 'long', year: 'numeric' }) : '—');
const { loginEmail } = useAuthModal();

// The system emails a 6-digit password; registrants do not choose one (config/registration.php)
const sent = ref(null); // { email, devPassword } once the password was emailed
watch(() => props.show, (open) => open && (sent.value = null));
const form = useForm({
    email: '',
    name: '',
    affiliation: '',
    affiliation_other: '', // typed name when the affiliation is not in the list
    phone: '', // optional
    // Sign-up is for authors only since 2026-11-01 (client request); admin can still add attend-only registrants
    participation: 'attend_submit',
    ...mealFormFields(), // own meal + one optional companion
    pdpa: false, // consent to the privacy policy (/privacy)
});

function submit() {
    form.transform((data) => ({
        ...data,
        ...mealPayload(data),
        affiliation_other: data.affiliation === OTHER_AFFILIATION ? data.affiliation_other : null,
    })).post('/register', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            sent.value = page.props.flash.passwordSent;
        },
    });
}

function close() {
    // Not while the account is being created (the email is on its way)
    if (form.processing) return;
    form.clearErrors();
    emit('close');
}

function goToLogin() {
    loginEmail.value = sent.value?.email ?? '';
    emit('switch');
}
</script>

<template>
    <Modal :show="show" max-width="max-w-xl" @close="close">
        <Transition name="fade" mode="out-in">
            <div v-if="sent" key="sent" class="p-6 sm:p-8">
                <h2 class="mb-6 text-center text-2xl font-semibold">{{ t('auth.registeredTitle') }}</h2>
                <PasswordSentNotice :email="sent.email" :dev-password="sent.devPassword" />
                <button
                    type="button"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition hover:bg-gold-300"
                    @click="goToLogin"
                >
                    <Fa icon="right-to-bracket" /> {{ t('auth.login') }}
                </button>
            </div>
            <div v-else-if="registration.state !== 'open'" key="closed" class="p-6 text-center sm:p-8">
                <span class="mx-auto mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="calendar-days" />
                </span>
                <h2 class="text-2xl font-semibold">{{ t(registration.state === 'upcoming' ? 'auth.registrationUpcoming' : 'auth.registrationClosed') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ t('auth.registrationClosedText', { start: longDate(registration.start), end: longDate(registration.end) }) }}</p>
                <button
                    type="button"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition hover:bg-gold-300"
                    @click="emit('switch')"
                >
                    <Fa icon="right-to-bracket" /> {{ t('auth.login') }}
                </button>
            </div>
            <form v-else key="form" class="relative p-6 sm:p-8" :aria-busy="form.processing" @submit.prevent="submit">
                <!-- While sending: a moving bar on top, the form fades and locks, only the button stays bright -->
                <div v-if="form.processing" class="loading-bar absolute inset-x-0 top-0 h-1 overflow-hidden rounded-t-2xl bg-gold-400/15" aria-hidden="true" />
                <fieldset :disabled="form.processing" :class="form.processing ? 'pointer-events-none opacity-30' : ''" class="min-w-0 transition-opacity duration-300">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="user-plus" />
                </span>
                <h2 class="text-2xl font-semibold">{{ t('auth.registerTitle') }}</h2>
                <p class="mt-1 mb-6 text-sm text-neutral-400">{{ t('auth.registerSubtitle') }}</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField class="sm:col-span-2" :label="t('auth.email')" icon="envelope" :error="form.errors.email" v-slot="{ inputClass }">
                        <input v-model="form.email" type="email" autocomplete="email" :class="inputClass" placeholder="name@example.com" required>
                    </FormField>

                    <FormField class="sm:col-span-2" :label="t('auth.name')" icon="user" :error="form.errors.name" v-slot="{ inputClass }">
                        <input v-model="form.name" type="text" autocomplete="name" :class="inputClass" :placeholder="t('auth.namePlaceholder')" required>
                    </FormField>

                    <FormField class="sm:col-span-2" :label="t('auth.phoneOptional')" icon="phone" :error="form.errors.phone" v-slot="{ inputClass }">
                        <input v-model="form.phone" type="tel" autocomplete="tel" :class="inputClass" :placeholder="t('auth.phonePlaceholder')">
                    </FormField>

                    <AffiliationField class="sm:col-span-2" :form="form" required />

                    <MealFields class="sm:col-span-2" :form="form" />

                    <!-- PDPA consent (required) -->
                    <label class="flex items-start gap-3 rounded-lg border p-3 text-sm sm:col-span-2" :class="form.errors.pdpa ? 'border-red-500/70' : 'border-white/10'">
                        <input v-model="form.pdpa" type="checkbox" class="mt-0.5 size-4 shrink-0 accent-gold-400">
                        <span class="text-neutral-300">
                            {{ t('auth.pdpaConsent') }}
                            <a href="/privacy" target="_blank" class="text-gold-400 underline hover:text-gold-300">{{ t('privacy.title') }}</a>
                            <span v-if="form.errors.pdpa" class="mt-1 block text-xs text-red-400">{{ t(form.errors.pdpa) }}</span>
                        </span>
                    </label>

                    <p class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-xs text-neutral-400 sm:col-span-2">
                        <Fa icon="envelope" class="mr-1 text-gold-400" /> {{ t('auth.passwordByEmail') }}
                    </p>
                </div>
                </fieldset>

                <button
                    type="submit"
                    :class="form.processing ? 'cursor-wait ring-4 ring-gold-400/30' : 'hover:bg-gold-300'"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition"
                    :disabled="form.processing"
                >
                    <Fa :icon="form.processing ? 'spinner' : 'user-plus'" :spin="form.processing" />
                    {{ form.processing ? t('auth.registering') : t('auth.register') }}
                </button>
                <!-- Sending the password email takes a few seconds: say so, so nobody presses again -->
                <Transition name="fade">
                    <p v-if="form.processing" class="mt-3 text-center text-sm text-gold-300" role="status">{{ t('auth.sendingWait') }}</p>
                </Transition>

                <p :class="form.processing ? 'pointer-events-none opacity-30' : ''" class="mt-5 text-center text-sm text-neutral-400 transition-opacity">
                    {{ t('auth.haveAccount') }}
                    <button type="button" class="font-medium text-gold-400 hover:underline" @click="emit('switch')">
                        {{ t('auth.login') }}
                    </button>
                </p>
            </form>
        </Transition>
    </Modal>
</template>
