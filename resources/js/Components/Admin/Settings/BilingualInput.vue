<script setup>
import { useI18n } from '@/i18n';

// One text in both languages, Thai and English side by side (v-model: { th, en })
const props = defineProps({
    modelValue: { type: Object, required: true },
    label: { type: String, required: true },
    errors: { type: Object, default: () => ({}) }, // form errors
    field: { type: String, required: true }, // error key prefix, e.g. 'fullName' -> fullName.th / fullName.en
    multiline: { type: Boolean, default: false },
    maxlength: { type: Number, default: 255 },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();
const set = (lang, value) => emit('update:modelValue', { ...props.modelValue, [lang]: value });

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
</script>

<template>
    <div>
        <p class="mb-1.5 text-sm font-medium">{{ label }}</p>
        <div class="grid gap-3 md:grid-cols-2">
            <label v-for="lang in ['th', 'en']" :key="lang" class="block">
                <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t(`lang.${lang}`) }}</span>
                <textarea
                    v-if="multiline"
                    :value="modelValue[lang]"
                    rows="3"
                    :maxlength="maxlength"
                    :class="[input, border(errors[`${field}.${lang}`])]"
                    @input="set(lang, $event.target.value)"
                />
                <input v-else :value="modelValue[lang]" :maxlength="maxlength" :class="[input, border(errors[`${field}.${lang}`])]" @input="set(lang, $event.target.value)">
                <span v-if="errors[`${field}.${lang}`]" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ t(errors[`${field}.${lang}`]) }}</span>
            </label>
        </div>
    </div>
</template>
