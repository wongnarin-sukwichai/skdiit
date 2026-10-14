<script setup>
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';

// Attend-only: registered. Author: the latest paper status, or "no paper yet".
defineProps({
    registrant: { type: Object, required: true },
});

const plain = 'inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap';
</script>

<template>
    <SubmissionStatusBadge v-if="registrant.paperStatus" :status="registrant.paperStatus" />
    <span
        v-else-if="registrant.participation === 'attend_submit'"
        :class="plain"
        class="border-neutral-300 bg-neutral-100 text-neutral-600 dark:border-white/20 dark:bg-white/10 dark:text-neutral-300"
    >
        {{ $t('admin.registrants.notSubmitted') }}
    </span>
    <span v-else :class="plain" class="border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-500/40 dark:bg-teal-500/15 dark:text-teal-300">
        {{ $t('admin.registrants.registered') }}
    </span>
</template>
