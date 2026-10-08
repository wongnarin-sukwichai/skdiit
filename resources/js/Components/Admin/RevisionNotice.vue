<script setup>
import { computed } from 'vue';
import { useI18n } from '@/i18n';

// Author-facing banner for a paper's outcome: revision requested, accepted or rejected
const props = defineProps({
    status: { type: String, required: true },
    comment: { type: String, default: null }, // the editor's latest message
    dueDate: { type: String, default: null }, // revision due date (Y-m-d)
    inForm: { type: Boolean, default: false }, // wording for the edit form
});

const { t, formatDate } = useI18n();

const tone = computed(() => ({
    screening_revision: 'orange',
    revision_minor: 'orange',
    revision_major: 'orange',
    accepted: 'green',
    screening_rejected: 'red',
    rejected: 'red',
}[props.status]));

const styles = {
    orange: 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-400/30 dark:bg-orange-400/10 dark:text-orange-200',
    green: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200',
    red: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
};
const icons = { orange: 'pen', green: 'circle-check', red: 'circle-exclamation' };

// The whole due day counts
const overdue = computed(() => props.dueDate && new Date(`${props.dueDate}T23:59:59`) < new Date());
</script>

<template>
    <div v-if="tone" :class="styles[tone]" class="rounded-xl border px-4 py-3 text-sm">
        <p class="flex items-start gap-3 font-medium">
            <Fa :icon="icons[tone]" class="mt-0.5" />
            {{ t(`submissions.outcome.${inForm ? 'form' : 'page'}.${status}`) }}
        </p>
        <p v-if="dueDate" class="mt-1 ml-7" :class="overdue ? 'font-semibold text-red-600 dark:text-red-400' : ''">
            <Fa icon="calendar-days" class="mr-1" />
            {{ t('submissions.outcome.due', { date: formatDate(dueDate, { day: 'numeric', month: 'long', year: 'numeric' }) }) }}
            <template v-if="overdue"> · {{ t('submissions.outcome.overdue') }}</template>
        </p>
        <blockquote v-if="comment" class="mt-2 ml-7 rounded-lg bg-white/70 px-3 py-2 whitespace-pre-line text-neutral-700 dark:bg-black/20 dark:text-neutral-200">{{ comment }}</blockquote>
    </div>
</template>
