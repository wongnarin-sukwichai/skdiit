<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { journeySteps } from '@/utils/journey';
import { useI18n } from '@/i18n';

/*
 * All of an author's papers on one path (author home, My submissions): the steps run across, and each paper sits
 * under the step it is on, with what the author has to do. A wide box lays the steps out in columns, a narrow one
 * stacks them. A paper the journal selected sits under "decision" (that is where its path splits off).
 */
const props = defineProps({
    papers: { type: Array, required: true }, // [{ id, code, title, status, canEdit, hadRevision, next }]
});

const { t, tr, formatDate } = useI18n();

const STEPS = [
    { key: 'submit', icon: 'file-arrow-up' },
    { key: 'screening', icon: 'magnifying-glass' },
    { key: 'peerReview', icon: 'user-check' },
    { key: 'result', icon: 'flag' },
    { key: 'final', icon: 'file-circle-check' },
    { key: 'present', icon: 'person-chalkboard' },
];

// The step each paper is on (journeySteps' current or failed step), and whether it ended there
function placeOf(paper) {
    const steps = journeySteps({ status: paper.status, editable: paper.canEdit && paper.status === 'submitted', hadRevision: paper.hadRevision, finalApproved: !!paper.cameraReadyApproved, finalFix: !!paper.cameraReadyFix });
    const now = steps.find((s) => ['current', 'failed'].includes(s.state)) ?? steps[steps.length - 1];
    return { key: now.key === 'journal' ? 'result' : now.key, failed: now.state === 'failed', journal: now.key === 'journal' };
}

const columns = computed(() => STEPS.map((step) => ({
    ...step,
    papers: props.papers.map((paper) => ({ ...paper, place: placeOf(paper) })).filter((paper) => paper.place.key === step.key),
})));
// Your turn somewhere in this step
const hasMine = (column) => column.papers.some((p) => p.next.mine);
// Steps every paper has passed (before the earliest step any paper is on): green, with a green line onwards
const firstBusy = computed(() => {
    const i = columns.value.findIndex((c) => c.papers.length);
    return i === -1 ? 0 : i;
});
const passed = (i) => i < firstBusy.value;

// The second line: the author's task (with its due date), or where the paper stands (same words as AuthorNextStep)
function statusText(paper) {
    if (paper.place.failed) return t('submissions.journey.note.notAccepted');
    const { next } = paper;
    if (!next.mine) return t(`submissions.next.task.${next.task}`);
    const task = t(`submissions.waiting.task.${next.task}`);
    if (next.daysLate) return `${task} · ${t('submissions.next.late', { n: next.daysLate })}`;
    return next.due ? `${task} · ${t('submissions.next.due', { date: formatDate(next.due) })}` : task;
}
const statusTone = (paper) => (paper.place.failed
    ? 'text-red-600 dark:text-red-400'
    : paper.next.daysLate ? 'font-medium text-red-600 dark:text-red-400'
        : paper.next.mine ? 'font-medium text-sky-700 dark:text-sky-300' : 'text-neutral-500 dark:text-neutral-400');
</script>

<template>
    <div class="@container">
        <ol class="grid gap-4 @3xl:grid-cols-6 @3xl:gap-0">
            <li v-for="(column, i) in columns" :key="column.key" class="relative min-w-0 @3xl:px-2">
                <!-- Step head: a line to the next step on a wide box -->
                <span
                    v-if="i < columns.length - 1"
                    :class="passed(i) ? 'bg-emerald-500' : 'bg-neutral-200 dark:bg-white/10'"
                    class="absolute top-4.5 right-[calc(-50%+1.25rem)] left-[calc(50%+1.25rem)] hidden h-0.5 @3xl:block"
                />
                <div class="flex items-center gap-3 @3xl:flex-col @3xl:gap-2 @3xl:text-center">
                    <span
                        :class="column.papers.length
                            ? (hasMine(column) ? 'border-sky-400 bg-sky-400 text-white' : 'border-gold-400 bg-gold-400 text-black')
                            : passed(i) ? 'border-emerald-500 bg-emerald-500 text-white'
                                : 'border-neutral-300 bg-white text-neutral-400 dark:border-white/15 dark:bg-ink-900 dark:text-neutral-500'"
                        class="relative z-10 grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm"
                    >
                        <span v-if="column.papers.length" class="font-bold">{{ column.papers.length }}</span>
                        <Fa v-else :icon="passed(i) ? 'check' : column.icon" />
                    </span>
                    <span :class="column.papers.length ? 'font-semibold' : passed(i) ? 'text-neutral-700 dark:text-neutral-200' : 'text-neutral-400 dark:text-neutral-500'" class="text-sm">{{ t(`submissions.journey.step.${column.key}`) }}</span>
                </div>

                <!-- The papers on this step -->
                <!-- Two lines per paper: code and short title, then where it stands -->
                <ul v-if="column.papers.length" class="mt-2 ml-12 space-y-1.5 @3xl:mt-3 @3xl:ml-0">
                    <li v-for="paper in column.papers" :key="paper.id">
                        <Link
                            :href="`/admin/my-submissions/${paper.id}`"
                            :title="`${paper.code} ${tr(paper.title)}`"
                            :class="paper.place.failed
                                ? 'border-red-200 bg-red-50/60 dark:border-red-500/30 dark:bg-red-500/10'
                                : paper.next.mine ? 'border-sky-400 bg-sky-50/60 dark:bg-sky-400/10' : 'border-neutral-200 dark:border-white/10'"
                            class="block rounded-md border px-2 py-1.5 text-left text-xs leading-snug transition hover:border-gold-400"
                        >
                            <span class="block truncate">
                                <span class="font-mono text-gold-600 dark:text-gold-400">{{ paper.code }}</span>
                                <span class="ml-1 font-medium">{{ tr(paper.title) }}</span>
                            </span>
                            <span :class="statusTone(paper)" class="block truncate">{{ statusText(paper) }}</span>
                        </Link>
                    </li>
                </ul>
            </li>
        </ol>
    </div>
</template>
