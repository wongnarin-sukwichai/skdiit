<script setup>
import { useI18n } from '@/i18n';

// Whose move it is on a paper (Submission::waitingOn): a pill with an icon (the status next to it is plain text)
defineProps({
    waiting: { type: Object, required: true }, // { who, task, done?, total?, late? }
});

const { t } = useI18n();

const styles = {
    editor: 'border-gold-400 bg-gold-50 text-gold-600 dark:border-gold-400/60 dark:bg-gold-400/10 dark:text-gold-300',
    reviewer: 'border-violet-300 bg-violet-50 text-violet-700 dark:border-violet-400/50 dark:bg-violet-400/10 dark:text-violet-200',
    author: 'border-sky-300 bg-sky-50 text-sky-700 dark:border-sky-400/50 dark:bg-sky-400/10 dark:text-sky-200',
    journal: 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/50 dark:bg-emerald-400/10 dark:text-emerald-200',
};
const icons = { editor: 'user-tie', reviewer: 'user-check', author: 'user-pen', journal: 'newspaper' };
</script>

<template>
    <span v-if="waiting.who === 'none'" class="text-neutral-400 dark:text-neutral-500" :title="t(`submissions.waiting.task.${waiting.task}`)">—</span>
    <span
        v-else
        :class="styles[waiting.who]"
        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-0.5 text-xs whitespace-nowrap"
        :title="t(`submissions.waiting.task.${waiting.task}`, waiting)"
    >
        <Fa :icon="icons[waiting.who]" class="text-[0.7rem]" />
        {{ t(`submissions.waiting.who.${waiting.who}`) }}
    </span>
</template>
