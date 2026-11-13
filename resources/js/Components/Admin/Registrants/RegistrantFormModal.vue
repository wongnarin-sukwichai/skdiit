<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import AffiliationField from '@/Components/AffiliationField.vue';
import MealFields from '@/Components/MealFields.vue';
import { useI18n } from '@/i18n';
import { OTHER_AFFILIATION } from '@/composables/useAffiliations';
import { mealFormFields, mealPayload } from '@/composables/useMealOptions';

/**
 * Add or edit a registrant (RegistrantController store/update; same rules as signing up).
 * Every registrant is an author (attend-only removed 2026-11-13).
 * back to attend only is allowed while there is no paper.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    registrant: { type: Object, default: null }, // null = add
});
const emit = defineEmits(['close']);

const { t } = useI18n();
const form = useForm({ name: '', email: '', phone: '', affiliation: '', affiliation_other: '', participation: 'attend_submit', ...mealFormFields() });

const isCreate = computed(() => !props.registrant);

watch(() => props.show, (open) => {
    if (!open) return;
    const r = props.registrant;
    form.defaults({
        name: r?.name ?? '',
        email: r?.email ?? '',
        phone: r?.phone ?? '',
        affiliation: r?.affiliation ?? '',
        affiliation_other: r?.affiliation_other ?? '',
        participation: r?.participation ?? 'attend_submit',
        ...mealFormFields(r),
    });
    form.reset();
    form.clearErrors();
});

function submit() {
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    form.transform((data) => ({
        ...data,
        ...mealPayload(data),
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
                <AffiliationField :form="form" />

                <MealFields :form="form" />

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
