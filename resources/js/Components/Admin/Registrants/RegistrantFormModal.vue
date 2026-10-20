<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';
import { OTHER_AFFILIATION, useAffiliations } from '@/composables/useAffiliations';

/**
 * Add or edit a registrant (RegistrantController store/update; same rules as signing up).
 * Admin can switch the participation type later (the registrant is told by email and keeps their password);
 * back to attend only is allowed while there is no paper.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    registrant: { type: Object, default: null }, // null = add
});
const emit = defineEmits(['close']);

const { t, tr } = useI18n();
const { affiliations } = useAffiliations();
const form = useForm({ name: '', email: '', phone: '', affiliation: '', affiliation_other: '', participation: 'attend' });

const isCreate = computed(() => !props.registrant);
const hasPaper = computed(() => !!props.registrant?.paper);
const participationChanged = computed(() => !isCreate.value && form.participation !== props.registrant.participation);

watch(() => props.show, (open) => {
    if (!open) return;
    const r = props.registrant;
    form.defaults({
        name: r?.name ?? '',
        email: r?.email ?? '',
        phone: r?.phone ?? '',
        affiliation: r?.affiliation ?? '',
        affiliation_other: r?.affiliation_other ?? '',
        participation: r?.participation ?? 'attend',
    });
    form.reset();
    form.clearErrors();
});

function submit() {
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    form.transform((data) => ({
        ...data,
        name: data.name.trim(),
        email: data.email.trim().toLowerCase(),
        phone: data.phone.trim() || null,
        affiliation_other: data.affiliation === OTHER_AFFILIATION ? data.affiliation_other : null,
    }));
    isCreate.value ? form.post('/admin/registrants', options) : form.put(`/admin/registrants/${props.registrant.id}`, options);
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
                <FormField :label="t('auth.name')" icon="user" :error="form.errors.name" v-slot="{ inputClass }">
                    <input v-model="form.name" :class="inputClass" maxlength="255" autocomplete="off">
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField :label="t('auth.email')" icon="envelope" :error="form.errors.email" v-slot="{ inputClass }">
                        <input v-model="form.email" type="email" :class="inputClass" maxlength="255" autocomplete="off">
                    </FormField>
                    <FormField :label="t('admin.accounts.phone')" icon="phone" :error="form.errors.phone" v-slot="{ inputClass }">
                        <input v-model="form.phone" type="tel" maxlength="30" :placeholder="t('admin.accounts.optional')" :class="inputClass">
                    </FormField>
                </div>
                <FormField :label="t('auth.affiliation')" icon="building-columns" :error="form.errors.affiliation" v-slot="{ inputClass }">
                    <select v-model="form.affiliation" :class="[inputClass, 'appearance-none pr-9']">
                        <option value="" disabled>{{ t('auth.selectAffiliation') }}</option>
                        <option v-for="a in affiliations" :key="a.code" :value="a.code" class="bg-ink-800 text-white">{{ tr(a.name) }}</option>
                        <option :value="OTHER_AFFILIATION" class="bg-ink-800 text-white">{{ t('auth.affiliationOther') }}</option>
                    </select>
                </FormField>
                <FormField v-if="form.affiliation === OTHER_AFFILIATION" :label="t('auth.affiliationOtherName')" icon="pen" :error="form.errors.affiliation_other" v-slot="{ inputClass }">
                    <input v-model="form.affiliation_other" :placeholder="t('auth.affiliationOtherPlaceholder')" :class="inputClass" maxlength="255">
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
                    <p v-if="form.errors.participation" class="mt-1.5 text-xs text-red-400">{{ t(form.errors.participation) }}</p>
                    <Transition name="fade">
                        <p v-if="participationChanged && form.participation === 'attend_submit'" class="mt-2 rounded-lg border border-gold-400/30 bg-gold-400/5 px-3 py-2.5 text-xs text-neutral-300">
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
                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing">
                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
