<script setup>
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { today as todayIn } from '@/utils/timeline';

/*
 * Where a revision stands (2026-11-12), under the status badge as a line of coloured text with an icon (2026-11-13, the
 * same look as the journal answer on Selection): the author is revising (amber, with the due date; red once it has
 * passed), or a revision came back and waits for the editors ("revision N", blue); the camera-ready file: waiting for
 * the author (amber) / fixes (orange), waiting for the editors' check (blue), approved (green).
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
    // Waiting for the author's revision: plain text like the journal answer on Selection (2026-11-13, user request):
    // red with ⚠ once past the due date, amber with ⏳ before it
    if (['screening_revision', 'review_revision'].includes(props.status)) {
        const late = props.dueDate && props.dueDate < todayIn();
        return {
            plain: true,
            icon: late ? 'triangle-exclamation' : 'hourglass-half',
            tone: late ? 'font-semibold text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-300',
            text: late ? t('submissions.revisionBadge.late', { date: formatDate(props.dueDate) })
                : props.dueDate ? t('submissions.revisionBadge.revisingBy', { date: formatDate(props.dueDate) })
                    : t('submissions.revisionBadge.revising'),
        };
    }
    // Camera-ready (2026-11-12): waiting for the author (or their fixes), waiting for the editors' check, approved.
    // Plain text with an icon too (2026-11-13), the same look as the revision lines above
    const amber = 'text-amber-600 dark:text-amber-300';
    const sky = 'text-sky-600 dark:text-sky-300';
    if (props.status === 'accepted') {
        return props.cameraReadyFix
            ? { plain: true, icon: 'pen', tone: 'text-orange-600 dark:text-orange-300', text: t('submissions.revisionBadge.finalFix') }
            : { plain: true, icon: 'hourglass-half', tone: amber, text: t('submissions.revisionBadge.finalWaiting') };
    }
    if (props.status === 'camera_ready') {
        return props.cameraReadyApproved
            ? { plain: true, icon: 'circle-check', tone: 'text-emerald-600 dark:text-emerald-400', text: t('submissions.revisionBadge.finalApproved') }
            : { plain: true, icon: 'magnifying-glass', tone: sky, text: t('submissions.revisionBadge.finalCheck') };
    }
    if (props.revisionCount && ['screening', 'revised'].includes(props.status)) {
        return { plain: true, icon: 'rotate', tone: sky, text: t('submissions.revisionBadge.round', { n: props.revisionCount }) };
    }
    return null;
});
</script>

<template>
    <span v-if="badge" :class="badge.tone" class="block text-xs whitespace-nowrap">
        <Fa :icon="badge.icon" class="mr-1" />{{ badge.text }}
    </span>
</template>
