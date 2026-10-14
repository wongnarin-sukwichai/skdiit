<script setup>
defineProps({
    status: { type: String, required: true }, // paymentStatuses in data/mock.js
    plain: { type: Boolean, default: false }, // coloured text only, no pill (tables)
});

// Front is always dark; the backend switches light/dark
const styles = {
    awaiting_payment: 'bg-neutral-200 text-neutral-600 border-neutral-300 dark:bg-white/10 dark:text-neutral-300 dark:border-white/20',
    pending_review: 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/40',
    slip_rejected: 'bg-red-100 text-red-700 border-red-200 dark:bg-red-500/15 dark:text-red-300 dark:border-red-500/40',
    paid: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
};
const icons = { awaiting_payment: 'clock', pending_review: 'magnifying-glass', slip_rejected: 'circle-exclamation', paid: 'circle-check' };
const textOnly = (status) => styles[status].split(' ').filter((c) => /(^|:)text-/.test(c)).join(' ');
</script>

<template>
    <span v-if="plain" :class="textOnly(status)" class="text-sm font-medium whitespace-nowrap">{{ $t(`payment.status.${status}`) }}</span>
    <span v-else :class="styles[status]" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap">
        <Fa :icon="icons[status]" class="text-[0.7rem]" />
        {{ $t(`payment.status.${status}`) }}
    </span>
</template>
