<script setup>
defineProps({
    status: { type: String, required: true }, // App\Enums\SubmissionStatus
});

// Later workflow statuses get their colours here as they are built
const styles = {
    submitted: 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/40',
    screening_revision: 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/40',
    screening_rejected: 'bg-red-100 text-red-700 border-red-200 dark:bg-red-500/15 dark:text-red-300 dark:border-red-500/40',
    in_review: 'bg-gold-100 text-gold-700 border-gold-200 dark:bg-gold-500/15 dark:text-gold-300 dark:border-gold-500/40',
    revision_minor: 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/40',
    revision_major: 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/40',
    revised: 'bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:border-violet-500/40',
    accepted: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
    rejected: 'bg-red-100 text-red-700 border-red-200 dark:bg-red-500/15 dark:text-red-300 dark:border-red-500/40',
    option_a: 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/40',
    camera_ready: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
    option_b: 'bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:border-violet-500/40',
    journal_accepted: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
};
</script>

<template>
    <span :class="styles[status]" class="inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap">
        {{ $t(`submissions.status.${status}`) }}
    </span>
</template>
