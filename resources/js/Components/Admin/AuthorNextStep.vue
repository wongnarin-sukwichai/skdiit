<script setup>
import { useI18n } from '@/i18n';

// The next step on a paper as its author sees it (Submission::nextForAuthor): their task as a pill, otherwise who has it
defineProps({
    next: { type: Object, required: true }, // { mine, task, due?, daysLate? }
});

const { t, formatDate } = useI18n();
</script>

<template>
    <span v-if="next.mine" class="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-300 bg-sky-50 px-3 py-0.5 text-xs whitespace-nowrap text-sky-700 dark:border-sky-400/50 dark:bg-sky-400/10 dark:text-sky-200">
            <Fa icon="user-pen" class="text-[0.7rem]" /> {{ t(`submissions.waiting.task.${next.task}`) }}
        </span>
        <span v-if="next.daysLate" class="text-xs font-medium whitespace-nowrap text-red-600 dark:text-red-400">{{ t('submissions.next.late', { n: next.daysLate }) }}</span>
        <span v-else-if="next.due" class="text-xs whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ t('submissions.next.due', { date: formatDate(next.due) }) }}</span>
    </span>
    <span v-else class="text-sm whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ t(`submissions.next.task.${next.task}`) }}</span>
</template>
