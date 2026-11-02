<script setup>
import FormField from '@/Components/FormField.vue';
import MealChoice from '@/Components/MealChoice.vue';
import { useI18n } from '@/i18n';

/**
 * The registrant's meal and one optional companion (name + meal) the registrant fills in
 * (client request 2026-11-01). Works on a useForm object with the fields of mealFormFields().
 */
const props = defineProps({
    form: { type: Object, required: true },
});

const { t } = useI18n();

function addCompanion() {
    props.form.companion = true;
}
function removeCompanion() {
    Object.assign(props.form, { companion: false, companion_name: '', companion_meal: '', companion_meal_other: '' });
}
</script>

<template>
    <div class="space-y-4">
        <MealChoice :form="form" field="meal" :label="t('meals.yourMeal')" />

        <Transition name="fade" mode="out-in">
            <div v-if="form.companion" key="companion" class="space-y-4 rounded-xl border border-gold-400/30 bg-gold-400/5 p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gold-400"><Fa icon="user-group" /> {{ t('meals.companion') }}</p>
                    <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-neutral-400 transition hover:bg-white/10 hover:text-red-400" @click="removeCompanion">
                        <Fa icon="trash-can" /> {{ t('meals.removeCompanion') }}
                    </button>
                </div>
                <FormField :label="t('meals.companionName')" icon="user" :error="form.errors.companion_name" v-slot="{ inputClass }">
                    <input v-model="form.companion_name" type="text" maxlength="255" autocomplete="off" :class="inputClass" :placeholder="t('auth.namePlaceholder')">
                </FormField>
                <MealChoice :form="form" field="companion_meal" :label="t('meals.companionMeal')" />
            </div>
            <button
                v-else
                key="add"
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-white/20 px-4 py-3 text-sm text-neutral-300 transition hover:border-gold-400 hover:text-gold-400"
                @click="addCompanion"
            >
                <Fa icon="user-plus" /> {{ t('meals.addCompanion') }}
                <span class="text-xs text-neutral-500">{{ t('meals.companionLimit') }}</span>
            </button>
        </Transition>
    </div>
</template>
