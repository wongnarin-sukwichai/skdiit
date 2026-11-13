<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import BarList from '@/Components/Admin/Charts/BarList.vue';
import TrendChart from '@/Components/Admin/Charts/TrendChart.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { useMealOptions } from '@/composables/useMealOptions';

/*
 * Reports (admin, editor): totals and breakdowns of this conference year (real since 2026-11-13,
 * App\Support\Reports), each tab exportable to Excel.
 */
const props = defineProps({
    report: { type: Object, required: true },
});
const { t, tr } = useI18n();
const { mealName } = useMealOptions();
const { toast } = useToast();

const tabs = [
    { key: 'registrants', icon: 'users' },
    { key: 'papers', icon: 'file-lines' },
    { key: 'reviews', icon: 'clipboard-check' },
];
const tab = ref('registrants');

const r = props.report;
const trackName = (name) => (name ? tr(name) : t('submissions.noTrack'));

// ---- Registrants ----
const affiliationRows = computed(() => r.registrants.byAffiliation.map(([name, value], i) => ({ key: i, label: tr(name), value })));
const mealRows = computed(() => r.registrants.meals.map(([code, other, value], i) => ({ key: i, label: mealName(code, other), value })));

// ---- Papers ----
const trackRows = computed(() => r.papers.byTrack.map(([name, value], i) => ({ key: i, label: trackName(name), value })));
const paperAffiliationRows = computed(() => r.papers.byAffiliation.map(([name, value], i) => ({ key: i, label: tr(name), value })));
const criteriaRows = computed(() => r.reviews.criteria.filter(([, avg]) => avg !== null).map(([name, value], i) => ({ key: i, label: tr(name), value })));
const statusRows = computed(() => r.papers.byStatus.map(([key, value]) => ({ key, label: t(`submissions.status.${key}`), value })));
const routeRows = computed(() => r.papers.routes.map(([key, value]) => ({ key, label: t(`publication.route.${key}`), value })));
const decided = r.papers.kpis.accepted + r.papers.kpis.rejected;
const acceptance = decided ? Math.round((r.papers.kpis.accepted / decided) * 100) : 0;

// ---- Reviews ----
const recommendationRows = computed(() => r.reviews.recommendations.map(([key, value]) => ({ key, label: t(`reviews.recommendation.${key}`), value })));
const reviewers = computed(() => [...r.reviews.reviewers].sort((a, b) => b.late - a.late || b.avgDays - a.avgDays));

const kpis = computed(() => ({
    registrants: { stats: r.registrants.kpis, cards: [['total', 'users'], ['papers', 'file-arrow-up'], ['notSignedIn', 'user-clock'], ['companions', 'user-plus']] },
    papers: { stats: { ...r.papers.kpis, acceptance }, cards: [['submitted', 'file-lines'], ['inReview', 'clipboard-check'], ['accepted', 'circle-check'], ['acceptance', 'percent']] },
    reviews: { stats: r.reviews.kpis, cards: [['assigned', 'user-check'], ['done', 'circle-check'], ['avgDays', 'clock'], ['overdue', 'triangle-exclamation']] },
}[tab.value]));

// ---- Excel: the current tab's tables, in the viewer's language ----
const exporting = ref(false);
function sheet() {
    const label = t('admin.reports.item');
    const count = t('admin.reports.count');
    const rowsOf = (title, list, unit = '') => [[title, ''], ...list.map((x) => [x.label, `${x.value}${unit}`]), ['', '']];
    if (tab.value === 'registrants') {
        return { headers: [label, count], rows: [...rowsOf(t('admin.reports.byAffiliation'), affiliationRows.value), ...rowsOf(t('admin.reports.meals'), mealRows.value), [t('admin.reports.daily'), ''], ...r.registrants.trend.map((p) => [p.date, String(p.value)])] };
    }
    if (tab.value === 'papers') {
        return { headers: [label, count], rows: [...rowsOf(t('admin.reports.byTrack'), trackRows.value), ...rowsOf(t('admin.reports.byStatus'), statusRows.value), ...rowsOf(t('admin.reports.byRoute'), routeRows.value), ...rowsOf(t('admin.reports.byAuthorAffiliation'), paperAffiliationRows.value), [t('admin.reports.byJournal'), `${t('admin.reports.offered')} / ${t('admin.reports.journalAccepted')}`], ...r.papers.byJournal.map((j) => [j.name, `${j.offered} / ${j.accepted}`])] };
    }
    return {
        headers: [t('admin.reports.reviewer'), t('submissions.fields.track'), t('admin.reports.assigned'), t('admin.reports.done'), t('admin.reports.avgDays'), t('admin.reports.late')],
        rows: [...reviewers.value.map((x) => [x.name, x.tracks.map(trackName).join(', '), String(x.assigned), String(x.done), String(x.avgDays), String(x.late)]), ['', '', '', '', '', ''], [t('admin.reports.criteriaAvg'), '', '', '', '', ''], ...criteriaRows.value.map((c) => [c.label, '', '', '', String(c.value), ''])],
    };
}

async function exportExcel() {
    exporting.value = true;
    try {
        const xsrf = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        const response = await fetch('/admin/export', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ ...sheet(), name: `report-${tab.value}` }),
        });
        if (!response.ok) throw new Error(response.statusText);
        const name = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? 'report.xlsx';
        const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await response.blob()), download: name });
        link.click();
        URL.revokeObjectURL(link.href);
        toast('admin.reports.exported');
    } catch {
        toast('admin.registrants.toast.exportFailed', {}, 'danger');
    } finally {
        exporting.value = false;
    }
}

const h2 = 'mb-4 text-base font-semibold';
</script>

<template>
    <Head :title="t('admin.menu.reports')" />

    <div class="space-y-6">
        <Card class="space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <PageHeader icon="chart-column" :title="t('admin.menu.reports')" :subtitle="t('admin.reports.subtitle')" class="flex-1" />
                <button
                    class="flex items-center gap-2 rounded-lg border border-emerald-600/40 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-400/40 dark:text-emerald-300 dark:hover:bg-emerald-400/10"
                    :disabled="exporting"
                    @click="exportExcel"
                >
                    <Fa icon="file-excel" /> {{ t('admin.reports.export', { tab: t(`admin.reports.tabs.${tab}`) }) }}
                </button>
            </div>

            <nav class="flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-white/10" role="tablist">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    role="tab"
                    :aria-selected="tab === item.key"
                    :class="tab === item.key
                        ? 'border-gold-400 text-gold-600 dark:text-gold-400'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                    class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                    @click="tab = item.key"
                >
                    <Fa :icon="item.icon" /> {{ t(`admin.reports.tabs.${item.key}`) }}
                </button>
            </nav>

            <Transition name="fade" mode="out-in">
                <StatCards :key="tab" :stats="kpis.stats" :cards="kpis.cards.map(([key, icon]) => ({ key, icon }))" />
            </Transition>
        </Card>

        <Transition name="fade" mode="out-in">
            <!-- Registrants -->
            <div v-if="tab === 'registrants'" key="registrants" class="grid gap-6 xl:grid-cols-2">
                <Card class="xl:col-span-2">
                    <h2 :class="h2">{{ t('admin.reports.dailySignups') }}</h2>
                    <TrendChart :points="r.registrants.trend" :label="t('admin.dash.people')" />
                </Card>
                <Card>
                    <h2 :class="h2">{{ t('admin.reports.byAffiliation') }}</h2>
                    <BarList :rows="affiliationRows" />
                </Card>
                <Card>
                    <h2 :class="h2">{{ t('admin.reports.meals') }}</h2>
                    <BarList v-if="mealRows.length" :rows="mealRows" />
                    <p v-else class="text-sm text-neutral-500">{{ t('admin.reports.noData') }}</p>
                    <p class="mt-6 text-sm text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.reports.mealsHint', { n: r.registrants.companions }) }}</p>
                </Card>
            </div>

            <!-- Papers -->
            <div v-else-if="tab === 'papers'" key="papers" class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <h2 :class="h2">{{ t('admin.reports.byTrack') }}</h2>
                    <BarList :rows="trackRows" />
                </Card>
                <Card>
                    <h2 :class="h2">{{ t('admin.reports.byRoute') }}</h2>
                    <BarList :rows="routeRows" />
                    <p class="mt-6 text-sm text-neutral-500 dark:text-neutral-400">
                        <Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.reports.acceptanceHint', { accepted: r.papers.kpis.accepted, decided, rate: acceptance }) }}
                    </p>
                </Card>
                <Card class="xl:col-span-2">
                    <h2 :class="h2">{{ t('admin.reports.byStatus') }}</h2>
                    <div class="grid gap-x-10 md:grid-cols-2">
                        <BarList :rows="statusRows.slice(0, 7)" :max="Math.max(1, ...statusRows.map((x) => x.value))" />
                        <BarList :rows="statusRows.slice(7)" :max="Math.max(1, ...statusRows.map((x) => x.value))" class="mt-2.5 md:mt-0" />
                    </div>
                </Card>
                <Card>
                    <h2 :class="h2">{{ t('admin.reports.byAuthorAffiliation') }}</h2>
                    <BarList v-if="paperAffiliationRows.length" :rows="paperAffiliationRows" />
                    <p v-else class="text-sm text-neutral-500">{{ t('admin.reports.noData') }}</p>
                </Card>
                <Card>
                    <h2 :class="h2">{{ t('admin.reports.byJournal') }}</h2>
                    <table v-if="r.papers.byJournal.length" class="w-full text-sm">
                        <thead class="text-left text-neutral-500 dark:text-neutral-400">
                            <tr><th class="py-2 font-medium">{{ t('admin.reports.journal') }}</th><th class="py-2 text-right font-medium">{{ t('admin.reports.offered') }}</th><th class="py-2 text-right font-medium">{{ t('admin.reports.journalAccepted') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                            <tr v-for="j in r.papers.byJournal" :key="j.name"><td class="py-2">{{ j.name }}</td><td class="py-2 text-right tabular-nums">{{ j.offered }}</td><td class="py-2 text-right tabular-nums">{{ j.accepted }}</td></tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-neutral-500">{{ t('admin.reports.noData') }}</p>
                </Card>
            </div>

            <!-- Reviews -->
            <div v-else key="reviews" class="grid gap-6 xl:grid-cols-[1fr_2fr]">
                <div class="space-y-6">
                    <Card>
                        <h2 :class="h2">{{ t('admin.reports.recommendations') }}</h2>
                        <BarList :rows="recommendationRows" />
                    </Card>
                    <Card>
                        <h2 :class="h2">{{ t('admin.reports.criteriaAvg') }}</h2>
                        <BarList v-if="criteriaRows.length" :rows="criteriaRows" :max="5" />
                        <p v-else class="text-sm text-neutral-500">{{ t('admin.reports.noData') }}</p>
                    </Card>
                </div>
                <Card>
                    <h2 :class="h2">{{ t('admin.reports.reviewerLoad') }}</h2>
                    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                        <table class="w-full min-w-170 text-sm">
                            <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                                <tr>
                                    <th class="px-4 py-3 font-medium">{{ t('admin.reports.reviewer') }}</th>
                                    <th class="px-3 py-3 text-right font-medium">{{ t('admin.reports.assigned') }}</th>
                                    <th class="px-3 py-3 font-medium">{{ t('admin.reports.done') }}</th>
                                    <th class="px-3 py-3 text-right font-medium">{{ t('admin.reports.avgDays') }}</th>
                                    <th class="px-3 py-3 text-right font-medium">{{ t('admin.reports.late') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                                <tr v-for="x in reviewers" :key="x.name">
                                    <td class="px-4 py-2.5">
                                        <div class="font-medium">{{ x.name }}</div>
                                        <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ x.tracks.map(trackName).join(' · ') }}</div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ x.assigned }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2" :title="`${x.done}/${x.assigned}`">
                                            <div class="h-2 w-24 rounded-full bg-neutral-100 dark:bg-white/5">
                                                <div class="h-full rounded-full bg-gold-400" :style="{ width: `${x.assigned ? (x.done / x.assigned) * 100 : 0}%` }" />
                                            </div>
                                            <span class="text-xs tabular-nums text-neutral-500 dark:text-neutral-400">{{ x.done }}/{{ x.assigned }}</span>
                                        </div>
                                    </td>
                                    <td :class="x.avgDays > 12 ? 'text-amber-700 dark:text-amber-300 font-semibold' : ''" class="px-3 py-2.5 text-right tabular-nums">{{ x.avgDays }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        <span v-if="x.late" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-500/15 dark:text-red-300"><Fa icon="triangle-exclamation" /> {{ x.late }}</span>
                                        <span v-else class="text-neutral-400">0</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

        </Transition>
    </div>
</template>
