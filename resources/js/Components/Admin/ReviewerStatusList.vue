<script setup>
import { useI18n } from '@/i18n';
import ReviewBadge from '@/Components/Admin/ReviewBadge.vue';

/*
 * This round's reviewers of a paper at a glance (Submissions list, 2026-11-12): each name with the recommendation once
 * sent, or the due date while reviewing (red when late), crossed out when they cannot review.
 */
defineProps({
    reviewers: { type: Array, default: () => [] }, // [{ name, state, recommendation, due }]
});

const { t, formatDate } = useI18n();
const short = { day: 'numeric', month: 'short' };
const icons = { submitted: 'circle-check', pending: 'hourglass-half', overdue: 'triangle-exclamation', unavailable: 'user-xmark', closed: 'lock' };
const tones = {
    submitted: 'text-emerald-600 dark:text-emerald-400',
    pending: 'text-sky-600 dark:text-sky-400',
    overdue: 'text-red-600 dark:text-red-400',
    unavailable: 'text-neutral-400 line-through',
    closed: 'text-neutral-400',
};
</script>

<template>
    <span v-if="!reviewers.length" class="text-neutral-400 dark:text-neutral-500">—</span>
    <ul v-else class="space-y-1">
        <li v-for="(r, i) in reviewers" :key="i" class="flex items-center gap-1.5 text-xs whitespace-nowrap" :title="t(`reviews.state.${r.state}`)">
            <Fa :icon="icons[r.state]" :class="tones[r.state]" class="w-3.5 shrink-0" />
            <span :class="r.state === 'unavailable' ? 'line-through text-neutral-400' : 'text-neutral-700 dark:text-neutral-200'" class="max-w-32 truncate">{{ r.name }}</span>
            <ReviewBadge v-if="r.recommendation" kind="recommendation" :value="r.recommendation" class="px-2! py-0! text-[11px]!" />
            <span v-else-if="r.state === 'pending' || r.state === 'overdue'" :class="tones[r.state]">{{ formatDate(r.due, short) }}</span>
            <span v-else :class="tones[r.state]">{{ t(`reviews.state.${r.state}`) }}</span>
        </li>
    </ul>
</template>
