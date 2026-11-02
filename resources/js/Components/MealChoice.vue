<script setup>
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { OTHER_MEAL, useMealOptions } from '@/composables/useMealOptions';

// One person's meal as chips (every choice in sight), with a text box under "Other"; part of MealFields
const props = defineProps({
    form: { type: Object, required: true },
    field: { type: String, required: true }, // 'meal' | 'companion_meal'; the typed text is `${field}_other`
    label: { type: String, required: true },
});

const { t, tr } = useI18n();
const { mealOptions } = useMealOptions();

const otherField = computed(() => `${props.field}_other`);
const choices = computed(() => [...mealOptions.value.map((m) => ({ code: m.code, name: tr(m.name) })), { code: OTHER_MEAL, name: t('meals.otherSpecify') }]);
const error = computed(() => props.form.errors[props.field] || props.form.errors[otherField.value]);
</script>

<template>
    <fieldset>
        <legend class="mb-1.5 text-sm text-neutral-300">{{ label }}</legend>
        <div class="flex flex-wrap gap-2">
            <label
                v-for="c in choices"
                :key="c.code"
                :class="form[field] === c.code ? 'border-gold-400 bg-gold-400/15 text-gold-300' : 'border-white/10 text-neutral-300 hover:border-white/30'"
                class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition has-focus-visible:ring-2 has-focus-visible:ring-gold-400/40"
            >
                <input v-model="form[field]" type="radio" :name="field" :value="c.code" class="sr-only">
                <Fa v-if="form[field] === c.code" icon="check" class="text-xs" />
                {{ c.name }}
            </label>
        </div>
        <Transition name="fade">
            <input
                v-if="form[field] === OTHER_MEAL"
                v-model="form[otherField]"
                type="text"
                maxlength="255"
                :placeholder="t('meals.otherPlaceholder')"
                :class="form.errors[otherField] ? 'border-red-500/70' : 'border-white/10'"
                class="mt-2 w-full rounded-lg border bg-ink-800 px-3 py-2.5 text-sm text-white placeholder-neutral-500 outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20"
            >
        </Transition>
        <Transition name="fade">
            <span v-if="error" class="mt-1 block text-xs text-red-400">{{ t(error) }}</span>
        </Transition>
    </fieldset>
</template>
