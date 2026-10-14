<script setup>
import { computed, reactive, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';
import { affiliations } from '@/data/mock';

/**
 * Add or edit a registrant (mock phase: validated here, saved by the page into the sample list).
 * Admin can switch the participation type later (the registrant is told by email and keeps their password);
 * back to attend only is allowed while there is no paper.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    registrant: { type: Object, default: null }, // null = add
    emailTaken: { type: Function, required: true }, // (email, exceptId) => boolean
});
const emit = defineEmits(['close', 'save']);

const { t, tr } = useI18n();
const form = reactive({ name: '', email: '', affiliation: '', participation: 'attend' });
const errors = reactive({});

const isCreate = computed(() => !props.registrant);
const hasPaper = computed(() => !!props.registrant?.paperStatus);
const participationChanged = computed(() => !isCreate.value && form.participation !== props.registrant.participation);

watch(() => props.show, (open) => {
    if (!open) return;
    Object.keys(errors).forEach((key) => delete errors[key]);
    Object.assign(form, {
        name: props.registrant ? tr(props.registrant.name) : '',
        email: props.registrant?.email ?? '',
        affiliation: props.registrant?.affiliation ?? '',
        participation: props.registrant?.participation ?? 'attend',
    });
});

// Same rules as AuthController::register
function validate() {
    Object.keys(errors).forEach((key) => delete errors[key]);
    const email = form.email.trim().toLowerCase();
    if (!form.name.trim()) errors.name = 'validation.required';
    if (!email) errors.email = 'validation.required';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'validation.email';
    else if (props.emailTaken(email, props.registrant?.id)) errors.email = 'validation.email_taken';
    if (!form.affiliation) errors.affiliation = 'validation.required';
    return !Object.keys(errors).length;
}

function submit() {
    if (!validate()) return;
    emit('save', { ...form, name: form.name.trim(), email: form.email.trim().toLowerCase() });
}
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <form class="p-6 sm:p-8" novalidate @submit.prevent="submit">
            <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                <Fa :icon="isCreate ? 'user-plus' : 'user-pen'" />
            </span>
            <h2 class="mb-6 text-2xl font-semibold">{{ isCreate ? t('admin.registrants.add') : t('admin.registrants.form.editTitle') }}</h2>

            <div class="space-y-4">
                <FormField :label="t('auth.name')" icon="user" :error="errors.name" v-slot="{ inputClass }">
                    <input v-model="form.name" :class="inputClass" autocomplete="off">
                </FormField>
                <FormField :label="t('auth.email')" icon="envelope" :error="errors.email" v-slot="{ inputClass }">
                    <input v-model="form.email" type="email" :class="inputClass" autocomplete="off">
                </FormField>
                <FormField :label="t('auth.affiliation')" icon="building-columns" :error="errors.affiliation" v-slot="{ inputClass }">
                    <select v-model="form.affiliation" :class="inputClass">
                        <option value="" disabled>{{ t('auth.selectAffiliation') }}</option>
                        <option v-for="a in affiliations" :key="a.key" :value="a.key">{{ tr(a) }}</option>
                    </select>
                </FormField>

                <div>
                    <span class="mb-1.5 block text-sm text-neutral-300">{{ t('registrants.participation') }}</span>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="value in ['attend', 'attend_submit']"
                            :key="value"
                            :class="[
                                form.participation === value ? 'border-gold-400 bg-gold-400/10' : 'border-white/10 hover:border-white/30',
                                value === 'attend' && hasPaper && 'cursor-not-allowed opacity-45 hover:border-white/10',
                            ]"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition"
                        >
                            <input v-model="form.participation" type="radio" :value="value" :disabled="value === 'attend' && hasPaper" class="accent-gold-400">
                            {{ t(`admin.registrants.participationType.${value}`) }}
                        </label>
                    </div>
                    <p v-if="hasPaper" class="mt-1.5 text-xs text-neutral-500">{{ t('admin.registrants.form.participationHasPaper') }}</p>
                    <Transition name="fade">
                        <p v-if="participationChanged" class="mt-2 rounded-lg border border-gold-400/30 bg-gold-400/5 px-3 py-2.5 text-xs text-neutral-300">
                            <Fa icon="envelope" class="mr-1 text-gold-400" /> {{ t('admin.registrants.form.participationNotice') }}
                        </p>
                    </Transition>
                </div>

                <p v-if="isCreate" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-xs text-neutral-400">
                    <Fa icon="envelope" class="mr-1 text-gold-400" /> {{ t('admin.registrants.form.passwordNote') }}
                </p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
