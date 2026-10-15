<script setup>
import { ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import PasswordSentNotice from '@/Components/Front/PasswordSentNotice.vue';
import { useI18n } from '@/i18n';
import { useAuthModal } from '@/composables/useAuthModal';
import { OTHER_AFFILIATION, useAffiliations } from '@/composables/useAffiliations';

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'switch']);

const { t, tr } = useI18n();
const page = usePage();
const { loginEmail } = useAuthModal();
const { affiliations } = useAffiliations();

// The system emails a 6-digit password; registrants do not choose one (config/registration.php)
const sent = ref(null); // { email, devPassword } once the password was emailed
watch(() => props.show, (open) => open && (sent.value = null));
const form = useForm({
    email: '',
    name: '',
    affiliation: '',
    affiliation_other: '', // typed name when the affiliation is not in the list
    phone: '', // optional
    participation: 'attend',
    pdpa: false, // consent to the privacy policy (/privacy)
});

const participationOptions = [
    { value: 'attend', label: 'auth.attendOnly', hint: 'auth.attendOnlyHint', icon: 'users' },
    { value: 'attend_submit', label: 'auth.attendSubmit', hint: 'auth.attendSubmitHint', icon: 'file-arrow-up' },
];

function submit() {
    form.post('/register', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            sent.value = page.props.flash.passwordSent;
        },
    });
}

function close() {
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
            <form v-else key="form" class="p-6 sm:p-8" @submit.prevent="submit">
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

                    <FormField class="sm:col-span-2" :label="t('auth.affiliation')" icon="building-columns" :error="form.errors.affiliation" v-slot="{ inputClass }">
                        <select v-model="form.affiliation" :class="[inputClass, 'appearance-none pr-9', { 'text-neutral-500': !form.affiliation }]" required>
                            <option value="" disabled class="bg-ink-800">{{ t('auth.selectAffiliation') }}</option>
                            <option v-for="a in affiliations" :key="a.code" :value="a.code" class="bg-ink-800 text-white">{{ tr(a.name) }}</option>
                            <option :value="OTHER_AFFILIATION" class="bg-ink-800 text-white">{{ t('auth.affiliationOther') }}</option>
                        </select>
                        <Fa icon="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-xs text-neutral-500" />
                    </FormField>

                    <Transition name="fade">
                        <FormField v-if="form.affiliation === OTHER_AFFILIATION" class="sm:col-span-2" :label="t('auth.affiliationOtherName')" icon="pen" :error="form.errors.affiliation_other" v-slot="{ inputClass }">
                            <input v-model="form.affiliation_other" type="text" autocomplete="organization" :class="inputClass" :placeholder="t('auth.affiliationOtherPlaceholder')" required>
                        </FormField>
                    </Transition>

                    <fieldset class="sm:col-span-2">
                        <legend class="mb-1.5 text-sm text-neutral-300">{{ t('auth.participation') }}</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="opt in participationOptions"
                                :key="opt.value"
                                :class="form.participation === opt.value ? 'border-gold-400 bg-gold-400/10' : 'border-white/10 hover:border-white/25'"
                                class="flex cursor-pointer gap-3 rounded-xl border p-3 transition"
                            >
                                <input v-model="form.participation" type="radio" name="participation" :value="opt.value" class="mt-1 size-4 shrink-0 accent-gold-400">
                                <span>
                                    <span class="flex items-center gap-2 text-sm font-medium">
                                        <Fa :icon="opt.icon" class="text-gold-400" />
                                        {{ t(opt.label) }}
                                    </span>
                                    <span class="mt-0.5 block text-xs text-neutral-400">{{ t(opt.hint) }}</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <!-- PDPA consent (required) -->
                    <label class="flex items-start gap-3 rounded-lg border p-3 text-sm sm:col-span-2" :class="form.errors.pdpa ? 'border-red-500/70' : 'border-white/10'">
                        <input v-model="form.pdpa" type="checkbox" class="mt-0.5 size-4 shrink-0 accent-gold-400">
                        <span class="text-neutral-300">
                            {{ t('auth.pdpaConsent') }}
                            <a href="/privacy" target="_blank" class="text-gold-400 underline hover:text-gold-300">{{ t('privacy.title') }}</a>
                            <span v-if="form.errors.pdpa" class="mt-1 block text-xs text-red-400">{{ t(form.errors.pdpa) }}</span>
                        </span>
                    </label>

                    <Transition name="fade">
                        <p v-if="form.participation === 'attend_submit'" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-xs text-neutral-400 sm:col-span-2">
                            <Fa icon="envelope" class="mr-1 text-gold-400" /> {{ t('auth.passwordByEmail') }}
                        </p>
                    </Transition>
                </div>

                <button
                    type="submit"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Fa icon="user-plus" />
                    {{ t('auth.register') }}
                </button>

                <p class="mt-5 text-center text-sm text-neutral-400">
                    {{ t('auth.haveAccount') }}
                    <button type="button" class="font-medium text-gold-400 hover:underline" @click="emit('switch')">
                        {{ t('auth.login') }}
                    </button>
                </p>
            </form>
        </Transition>
    </Modal>
</template>
