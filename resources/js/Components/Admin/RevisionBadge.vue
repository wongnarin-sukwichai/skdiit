<script setup>
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { today as todayIn } from '@/utils/timeline';

/*
 * Where a revision stands (2026-11-12), next to the status badge: the author is revising (orange, with the due date;
 * red once it has passed), or a revision came back and waits for the editors ("revision N", blue); the camera-ready
 * file: waiting for the author / fixes (orange), waiting for the editors' check (blue), approved (green).
 */
const props = defineProps({
    status: { type: String, required: true },
    revisionCount: { type: Number, default: 0 }, // revisions the author has sent back
    dueDate: { type: String, default: null }, // revision due date (Y-m-d), when the editor set one
    cameraReadyApproved: { type: String, default: null }, // when the editors approved the camera-ready file
    cameraReadyFix: { type: Boolean, default: false }, // the editors sent the camera-ready file back for fixes
});

const { t, formatDate } = useI18n();

const badge = computed(() => {
    if (['screening_revision', 'review_revision'].includes(props.status)) {
        const late = props.dueDate && props.dueDate < todayIn();
        return {
            icon: late ? 'triangle-exclamation' : 'pen',
            tone: late ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-300' : 'border-orange-300 bg-orange-50 text-orange-700 dark:border-orange-500/40 dark:bg-orange-500/15 dark:text-orange-300',
            text: late ? t('submissions.revisionBadge.late', { date: formatDate(props.dueDate) })
                : props.dueDate ? `${t('submissions.revisionBadge.revising')} · ${t('submissions.revisionBadge.due', { date: formatDate(props.dueDate) })}`
                    : t('submissions.revisionBadge.revising'),
        };
    }
    // Camera-ready (2026-11-12): waiting for the author (or their fixes), waiting for the editors' check, approved
    const orange = 'border-orange-300 bg-orange-50 text-orange-700 dark:border-orange-500/40 dark:bg-orange-500/15 dark:text-orange-300';
    if (props.status === 'accepted') {
        return { icon: props.cameraReadyFix ? 'pen' : 'hourglass-half', tone: orange, text: t(props.cameraReadyFix ? 'submissions.revisionBadge.finalFix' : 'submissions.revisionBadge.finalWaiting') };
    }
    if (props.status === 'camera_ready') {
        return props.cameraReadyApproved
            ? { icon: 'circle-check', tone: 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300', text: t('submissions.revisionBadge.finalApproved') }
            : { icon: 'magnifying-glass', tone: 'border-sky-300 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-300', text: t('submissions.revisionBadge.finalCheck') };
    }
    if (props.revisionCount && ['screening', 'revised'].includes(props.status)) {
        return {
            icon: 'rotate',
            tone: 'border-sky-300 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-300',
            text: t('submissions.revisionBadge.round', { n: props.revisionCount }),
        };
    }
    return null;
});
</script>

<template>
    <span v-if="badge" :class="badge.tone" class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap">
        <Fa :icon="badge.icon" class="text-[0.65rem]" /> {{ badge.text }}
    </span>
</template>
