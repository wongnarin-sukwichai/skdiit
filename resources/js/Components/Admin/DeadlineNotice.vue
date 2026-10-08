<script setup>
import { useI18n } from '@/i18n';

defineProps({
    deadline: { type: String, default: null }, // ISO date-time, or null when none is set
    isOpen: { type: Boolean, required: true },
});

const { t, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <div
        :class="isOpen
            ? 'border-gold-300 bg-gold-50 text-gold-800 dark:border-gold-400/30 dark:bg-gold-400/10 dark:text-gold-200'
            : 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300'"
        class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
    >
        <Fa :icon="isOpen ? 'clock' : 'lock'" class="mt-0.5" />
        <p v-if="!deadline">{{ t('submissions.deadline.none') }}</p>
        <p v-else-if="isOpen">{{ t('submissions.deadline.open', { date: when(deadline) }) }}</p>
        <p v-else>{{ t('submissions.deadline.closed', { date: when(deadline) }) }}</p>
    </div>
</template>
