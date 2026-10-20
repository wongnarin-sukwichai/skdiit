<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import HorizontalTimeline from '@/Components/Admin/Timeline/HorizontalTimeline.vue';
import TimelinePhaseModal from '@/Components/Admin/Timeline/TimelinePhaseModal.vue';
import { timelinePlan } from '@/data/mock';
import { addDays, checkPlan, phaseState, spanDays, toDay, today as todayIn } from '@/utils/timeline';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

/*
 * The conference timeline (phase 1, 2026-10-20): assumed dates from mock.js laid out on a horizontal timeline, with the
 * rows the system enforces (submission period, journal selection, slides cut-off, conference day) taken from and saved to
 * their real settings (TimelinePhaseModal). Edits to the other rows only redraw the timeline and are not saved yet.
 * The important dates and day programme shown on the public site are website content (WebsiteTab › ScheduleContent).
 */
const props = defineProps({
    settings: { type: Object, required: true }, // SettingsController::index `submission`
});

const { t, formatDate } = useI18n();
const site = useSite();
const today = todayIn();

const fresh = () => timelinePlan.map((p) => ({ ...p, perItem: p.perItem && { ...p.perItem } }));

// Lay the real values over a plan; `real` rows save to the server, `source` says whether their setting is set yet
function overlay(plan) {
    const row = (key) => plan.find((p) => p.key === key);
    const s = props.settings;

    const conference = row('conference');
    if (site.value.conference?.date) Object.assign(conference, { start: site.value.conference.date, end: site.value.conference.date });
    Object.assign(conference, { real: true, source: 'set' });

    const submission = row('submission');
    if (s.opens) submission.start = s.opens.slice(0, 10);
    if (s.deadline) submission.end = s.deadline.slice(0, 10);
    Object.assign(submission, { real: true, source: s.opens || s.deadline ? 'set' : 'unset' });

    const journal = row('journalSelect');
    if (s.deadline) journal.start = addDays(submission.end, 1);
    if (s.journalDeadline) journal.end = s.journalDeadline;
    Object.assign(journal, { real: true, source: s.journalDeadline ? 'set' : 'unset' });

    const slides = row('slides');
    const cutoff = s.slidesCutoffDays === null || s.slidesCutoffDays === '' ? null : Number(s.slidesCutoffDays);
    if (cutoff !== null) slides.end = addDays(conference.start, -cutoff);
    Object.assign(slides, { real: true, source: cutoff !== null ? 'set' : 'unset' });

    return plan;
}

const plan = ref(overlay(fresh()));
const original = ref(JSON.stringify(plan.value));
const changed = computed(() => JSON.stringify(plan.value) !== original.value);
function reset() {
    plan.value = overlay(fresh());
    original.value = JSON.stringify(plan.value);
}
// A saved real value redraws its row and keeps the trial edits on the others
watch(() => [props.settings, site.value.conference?.date], () => {
    plan.value = overlay(JSON.parse(JSON.stringify(plan.value)));
    original.value = JSON.stringify(overlay(fresh()));
});

const label = (key) => t(`admin.timeline.phases.${key}`);
const phases = computed(() => plan.value.map((p) => ({ ...p, label: label(p.key) })));

// Problems in the order of the steps, worded for the reader
const edge = (key, which) => (which ? t(`admin.timeline.edge.${which}`, { name: label(key) }) : label(key));
const issues = computed(() => checkPlan(plan.value).map((issue) => {
    const p = issue.params;
    const text = {
        range: () => t('admin.timeline.issue.range', { a: label(p.a) }),
        short: () => t('admin.timeline.issue.short', { a: label(p.a), span: p.span, days: p.days, unit: t(`admin.timeline.unit.${p.unit}`) }),
    }[issue.type]?.() ?? t(`admin.timeline.issue.${issue.type}`, { a: edge(p.a, p.aEdge), aDate: formatDate(p.aDate), b: edge(p.b, p.bEdge), bDate: formatDate(p.bDate) });
    return { ...issue, text };
}));
const issueLevels = computed(() => issues.value.reduce((levels, issue) => {
    if (levels[issue.phase] !== 'error') levels[issue.phase] = issue.level;
    return levels;
}, {}));
const errorCount = computed(() => issues.value.filter((i) => i.level === 'error').length);

// What is running today, and what comes next
const active = computed(() => phases.value.filter((p) => phaseState(p, today) === 'active')
    .map((p) => ({ ...p, left: toDay(p.end) - toDay(today) })));
const next = computed(() => phases.value.filter((p) => p.start > today).sort((a, b) => a.start.localeCompare(b.start))[0]);

const editing = ref(null);
const editingPhase = computed(() => phases.value.find((p) => p.key === editing.value) ?? null);
const editingIssues = computed(() => issues.value.filter((i) => i.phase === editing.value));
function apply(change) {
    Object.assign(plan.value.find((p) => p.key === change.key), change);
    editing.value = null;
}
</script>

<template>
    <div class="space-y-8">
        <section class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 font-semibold"><Fa icon="chart-line" class="text-gold-500" /> {{ t('admin.timeline.title') }}</h3>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.timeline.intro') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/admin/preview/author-journey" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-xs transition hover:border-gold-400 dark:border-white/15">
                        <Fa icon="eye" /> {{ t('admin.timeline.authorPreview') }}
                    </Link>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-xs transition hover:border-gold-400 disabled:opacity-40 disabled:hover:border-neutral-300 dark:border-white/15"
                        :disabled="!changed"
                        @click="reset"
                    >
                        <Fa icon="arrow-rotate-right" /> {{ t('admin.timeline.reset') }}
                    </button>
                </div>
            </div>

            <p class="flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                <Fa icon="flask" class="mt-0.5 shrink-0" /> {{ t('admin.timeline.prototype') }}
            </p>

            <!-- Today -->
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-medium">{{ t('admin.timeline.todayIs', { date: formatDate(today) }) }}</span>
                <span v-for="p in active" :key="p.key" class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/50 bg-gold-50 px-3 py-1 text-xs text-gold-700 dark:bg-gold-400/10 dark:text-gold-300">
                    <Fa :icon="p.icon" /> {{ p.label }} · {{ t('admin.timeline.daysLeft', { days: p.left }) }}
                </span>
                <span v-if="!active.length" class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.timeline.nothingActive') }}</span>
                <span v-if="next" class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ t('admin.timeline.next', { name: next.label, date: formatDate(next.start), days: toDay(next.start) - toDay(today) }) }}
                </span>
            </div>

            <HorizontalTimeline :phases="phases" :issues="issueLevels" :today="today" @select="editing = $event" />

            <!-- Order problems and too-short phases -->
            <Transition name="fade" mode="out-in">
                <div v-if="issues.length" key="issues" class="rounded-xl border border-red-200 p-4 dark:border-red-500/30">
                    <p class="mb-2 text-sm font-semibold text-red-600 dark:text-red-400">
                        <Fa icon="circle-exclamation" /> {{ t('admin.timeline.issuesTitle', { errors: errorCount, warnings: issues.length - errorCount }) }}
                    </p>
                    <ul class="space-y-1">
                        <li v-for="(issue, i) in issues" :key="i">
                            <button type="button" :class="issue.level === 'error' ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'" class="flex gap-2 text-left text-xs hover:underline" @click="editing = issue.phase">
                                <Fa :icon="issue.level === 'error' ? 'circle-exclamation' : 'triangle-exclamation'" class="mt-0.5 shrink-0" /> {{ issue.text }}
                            </button>
                        </li>
                    </ul>
                </div>
                <p v-else key="ok" class="flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400">
                    <Fa icon="circle-check" /> {{ t('admin.timeline.allGood') }}
                </p>
            </Transition>

            <!-- Time each paper or reviewer gets inside its phase -->
            <ul class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                <li v-for="p in phases.filter((x) => x.perItem)" :key="p.key">
                    <Fa icon="hourglass-half" class="mr-1 text-gold-500" />
                    {{ t('admin.timeline.perItemSummary', { name: p.label, days: p.perItem.days, unit: t(`admin.timeline.unit.${p.perItem.unit}`), span: spanDays(p) }) }}
                </li>
            </ul>
        </section>

        <TimelinePhaseModal :phase="editingPhase" :issues="editingIssues" :settings="settings" @close="editing = null" @apply="apply" />
    </div>
</template>
