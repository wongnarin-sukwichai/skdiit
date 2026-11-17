<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import HorizontalTimeline from '@/Components/Admin/Timeline/HorizontalTimeline.vue';
import TimelinePhaseModal from '@/Components/Admin/Timeline/TimelinePhaseModal.vue';
import { useSchedulePlan } from '@/composables/useSchedulePlan';
import { checkPlan, phaseState, spanDays, toDay, today as todayIn, withRealDates } from '@/utils/timeline';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

/*
 * The conference timeline on a horizontal scale. Every row saves (TimelinePhaseModal): the rows the system enforces
 * (submission period, paper selection, the author's answer to a journal offer, slides cut-off, conference day) to their
 * own settings, the others to App\Support\SchedulePlan (2026-11-12; dates shown to authors, not enforced yet).
 * The important dates and day programme shown on the public site are website content (WebsiteTab › ScheduleContent).
 */
const props = defineProps({
    settings: { type: Object, required: true }, // SettingsController::index `submission`
});

const { t, formatDate } = useI18n();
const site = useSite();
const today = todayIn();

const schedule = useSchedulePlan();
const fresh = () => schedule.value.map((p) => ({ ...p, perItem: p.perItem && { ...p.perItem } }));

// Every row is saved, so the plan is always the saved one: redraw it after each save
const plan = computed(() => withRealDates(fresh(), props.settings, site.value.conference?.date));

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
                    <Link :href="$url('/admin/preview/author-journey')" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-xs transition hover:border-gold-400 dark:border-white/15">
                        <Fa icon="eye" /> {{ t('admin.timeline.authorPreview') }}
                    </Link>
                </div>
            </div>

            <p class="flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                <Fa icon="circle-info" class="mt-0.5 shrink-0" /> {{ t('admin.timeline.prototype') }}
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

        <TimelinePhaseModal :phase="editingPhase" :issues="editingIssues" :settings="settings" @close="editing = null" />
    </div>
</template>
