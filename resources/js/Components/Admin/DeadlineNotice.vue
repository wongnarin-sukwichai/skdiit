<script setup>
import { useI18n } from '@/i18n';

// Submission period from Submission::window()
defineProps({
    opensAt: { type: String, default: null }, // ISO date-time, or null when papers are accepted right away
    deadline: { type: String, default: null }, // ISO date-time, or null when none is set
    phase: { type: String, required: true }, // upcoming | open | closed
});

const { t, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const styles = {
    upcoming: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-200',
    open: 'border-gold-300 bg-gold-50 text-gold-800 dark:border-gold-400/30 dark:bg-gold-400/10 dark:text-gold-200',
    closed: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
};
const icons = { upcoming: 'calendar-days', open: 'clock', closed: 'lock' };
</script>

<template>
    <div :class="styles[phase]" class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm">
        <Fa :icon="icons[phase]" class="mt-0.5" />
        <p v-if="phase === 'upcoming'">
            {{ deadline ? t('submissions.deadline.upcomingUntil', { date: when(opensAt), deadline: when(deadline) }) : t('submissions.deadline.upcoming', { date: when(opensAt) }) }}
        </p>
        <p v-else-if="phase === 'closed'">{{ t('submissions.deadline.closed', { date: when(deadline) }) }}</p>
        <p v-else-if="deadline">{{ t('submissions.deadline.open', { date: when(deadline) }) }}</p>
        <p v-else>{{ t('submissions.deadline.none') }}</p>
    </div>
</template>
