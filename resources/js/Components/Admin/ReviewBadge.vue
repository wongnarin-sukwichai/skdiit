<script setup>
// A review's progress (pending / overdue / submitted) or a reviewer's recommendation
defineProps({
    kind: { type: String, required: true }, // 'state' | 'recommendation'
    value: { type: String, required: true },
});

const styles = {
    pending: 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/40',
    overdue: 'bg-red-100 text-red-700 border-red-200 dark:bg-red-500/15 dark:text-red-300 dark:border-red-500/40',
    closed: 'bg-neutral-200 text-neutral-600 border-neutral-300 dark:bg-white/10 dark:text-neutral-300 dark:border-white/20',
    submitted: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
    accept: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
    minor: 'bg-gold-100 text-gold-700 border-gold-200 dark:bg-gold-500/15 dark:text-gold-300 dark:border-gold-500/40',
    major: 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/40',
    reject: 'bg-red-100 text-red-700 border-red-200 dark:bg-red-500/15 dark:text-red-300 dark:border-red-500/40',
};
</script>

<template>
    <span :class="styles[value]" class="inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap">
        {{ $t(kind === 'state' ? `reviews.state.${value}` : `reviews.recommendation.${value}`) }}
    </span>
</template>
