<script setup>
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';
import { affiliations } from '@/data/mock';

defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'switch']);

const { t, tr } = useI18n();
const form = useForm({
    email: '',
    name: '',
    affiliation: '',
    participation: 'attend',
    password: '',
    password_confirmation: '',
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
            emit('close');
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

function close() {
    form.clearErrors();
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="max-w-xl" @close="close">
        <form class="p-6 sm:p-8" @submit.prevent="submit">
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

                <FormField class="sm:col-span-2" :label="t('auth.affiliation')" icon="building-columns" :error="form.errors.affiliation" v-slot="{ inputClass }">
                    <select v-model="form.affiliation" :class="[inputClass, 'appearance-none pr-9', { 'text-neutral-500': !form.affiliation }]" required>
                        <option value="" disabled class="bg-ink-800">{{ t('auth.selectAffiliation') }}</option>
                        <option v-for="a in affiliations" :key="a.key" :value="a.key" class="bg-ink-800 text-white">{{ tr(a) }}</option>
                    </select>
                    <Fa icon="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-xs text-neutral-500" />
                </FormField>

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

                <FormField :label="t('auth.password')" icon="lock" :error="form.errors.password" v-slot="{ inputClass }">
                    <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass" required>
                </FormField>
                <FormField :label="t('auth.passwordConfirm')" icon="lock" v-slot="{ inputClass }">
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="inputClass" required>
                </FormField>
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
    </Modal>
</template>
