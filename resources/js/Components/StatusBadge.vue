<script setup>
defineProps({
    status: { type: String, required: true },
});

// Front is always dark; the backend switches light/dark
const styles = {
    confirmed: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
    awaiting_submission: 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/40',
    under_review: 'bg-gold-100 text-gold-700 border-gold-200 dark:bg-gold-500/15 dark:text-gold-300 dark:border-gold-500/40',
    unconfirmed: 'bg-neutral-200 text-neutral-600 border-neutral-300 dark:bg-white/10 dark:text-neutral-300 dark:border-white/20',
};
</script>

<template>
    <span :class="styles[status]" class="inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap">
        {{ $t(`status.${status}`) }}
    </span>
</template>
