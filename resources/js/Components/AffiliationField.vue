<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/i18n';
import { OTHER_AFFILIATION, useAffiliations } from '@/composables/useAffiliations';

/**
 * Affiliation picker with an "Other, please specify" tick on the same row (client request 2026-11-01:
 * the option at the end of the list was easy to miss). Ticking it swaps the list for a text box.
 * Works on a useForm object with `affiliation` and `affiliation_other`.
 */
const props = defineProps({
    form: { type: Object, required: true },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false }, // may stay empty (reviewers): labelled so, with an empty choice
});

const { t, tr } = useI18n();
const { affiliations } = useAffiliations();

const isOther = computed(() => props.form.affiliation === OTHER_AFFILIATION);
// The list choice to restore when unticking (also after the form is reset)
const picked = ref('');
watch(() => props.form.affiliation, (value) => value !== OTHER_AFFILIATION && (picked.value = value), { immediate: true });
const toggleOther = (event) => (props.form.affiliation = event.target.checked ? OTHER_AFFILIATION : picked.value);

const error = computed(() => props.form.errors.affiliation || (isOther.value && props.form.errors.affiliation_other));
const fieldClass = computed(() => [
    'w-full rounded-lg border bg-ink-800 py-2.5 pr-3 pl-10 text-sm text-white placeholder-neutral-500 outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20',
    error.value ? 'border-red-500/70' : 'border-white/10',
]);
</script>

<template>
    <div>
        <span class="mb-1.5 block text-sm text-neutral-300">
            {{ isOther ? t('auth.affiliationOtherName') : t('auth.affiliation') }}
            <span v-if="optional" class="text-neutral-500">({{ t('admin.accounts.optional') }})</span>
        </span>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="relative block min-w-0 flex-1 basis-56">
                <Fa :icon="isOther ? 'pen' : 'building-columns'" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm text-neutral-500" />
                <Transition name="fade" mode="out-in">
                    <input
                        v-if="isOther"
                        key="typed"
                        v-model="form.affiliation_other"
                        type="text"
                        autocomplete="organization"
                        maxlength="255"
                        :class="fieldClass"
                        :placeholder="t('auth.affiliationOtherPlaceholder')"
                        :required="required"
                    >
                    <span v-else key="list" class="relative block">
                        <select v-model="form.affiliation" :class="[fieldClass, 'appearance-none pr-9', { 'text-neutral-500': !form.affiliation }]" :required="required">
                            <option value="" :disabled="!optional" class="bg-ink-800">{{ optional ? t('admin.accounts.noTitle') : t('auth.selectAffiliation') }}</option>
                            <option v-for="a in affiliations" :key="a.code" :value="a.code" class="bg-ink-800 text-white">{{ tr(a.name) }}</option>
                        </select>
                        <Fa icon="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-xs text-neutral-500" />
                    </span>
                </Transition>
            </span>
            <label class="flex shrink-0 items-center gap-2 text-sm text-neutral-300">
                <input type="checkbox" :checked="isOther" class="size-4 accent-gold-400" @change="toggleOther">
                {{ t('auth.affiliationOtherTick') }}
            </label>
        </div>
        <Transition name="fade">
            <span v-if="error" class="mt-1 block text-xs text-red-400">{{ t(error) }}</span>
        </Transition>
    </div>
</template>
