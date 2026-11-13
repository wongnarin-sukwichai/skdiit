<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import Pagination from '@/Components/Pagination.vue';
import FirstGateTable from '@/Components/Admin/Journal/FirstGateTable.vue';
import BulkReminderModal from '@/Components/Admin/BulkReminderModal.vue';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';

// Selection (admin, editors and the journal role alike, 2026-11-11): the first gate. After the submission deadline each
// paper gets its track and is offered to a partner journal (the author accepts or declines) or sent to the conference
const props = defineProps({
    papers: { type: Object, required: true }, // Laravel paginator
    filters: { type: Object, required: true }, // { q, tab, track, journal }
    counts: { type: Object, required: true }, // papers per tab
    tracks: { type: Array, required: true },
    journals: { type: Array, required: true }, // every partner journal, with `active`
    journalDeadline: { type: String, default: null }, // the first gate should be done by then (a warning only)
    answerDays: { type: Number, required: true },
    pendingOffers: { type: Object, default: () => ({ total: 0, overdue: 0 }) }, // offers waiting for the author's answer
    showUrl: { type: String, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
});

const { t, tr, formatDate, formatNumber, locale } = useI18n();
const { accessTo } = useModules();

const tabs = [
    { key: 'waiting', icon: 'inbox' },
    { key: 'late', icon: 'clock' },
    { key: 'offered', icon: 'hourglass-half' },
    { key: 'journal', icon: 'newspaper' },
    { key: 'conference', icon: 'arrow-right' },
    { key: 'all', icon: 'list' },
];
const q = ref(props.filters.q ?? '');
const tab = ref(props.filters.tab);
const track = ref(props.filters.track ?? '');
const journal = ref(props.filters.journal ?? '');

const query = (params = {}) => ({ q: q.value || undefined, tab: tab.value, track: track.value || undefined, journal: journal.value || undefined, ...params });
function load(params = {}) {
    router.get('/admin/selection', query(params), {
        only: ['papers', 'filters', 'counts'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function pickTab(key) {
    tab.value = key;
    load();
}
const page = computed({
    get: () => props.papers.current_page,
    set: (value) => load({ page: value }),
});

// Excel of every paper in this tab and filters (the server builds it, not only the page shown)
// Remind every author who has not answered a journal offer (editors and admin; confirmed first)
const canRemind = computed(() => accessTo('selection') === 'full');
const remindOpen = ref(false);

const exportUrl = computed(() => {
    const params = new URLSearchParams(Object.entries({ ...query(), lang: locale.value }).filter(([, v]) => v !== undefined));
    return `/admin/selection/export?${params}`;
});

// Only active journals are offered; inactive ones stay in the filter for papers already offered to them
const activeJournals = computed(() => props.journals.filter((j) => j.active));

const firstGatePast = computed(() => props.journalDeadline && new Date(props.journalDeadline) < new Date());
// Tabs that need someone: papers past the first-gate deadline show red
const badge = (key) => (!props.counts[key] || ['all', 'journal', 'conference'].includes(key)
    ? 'bg-neutral-100 text-neutral-500 dark:bg-white/10 dark:text-neutral-400'
    : key === 'late' ? 'bg-red-500 text-white' : 'bg-gold-400 text-black');
const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
</script>

<template>
    <Head :title="t('admin.menu.selection')" />

    <Card class="space-y-6">
        <PageHeader icon="code-branch" :title="t('admin.menu.selection')" :subtitle="t('selection.subtitle')" />

        <!-- Offers waiting for the author: nothing lapses on its own, the editors remind and move them -->
        <div v-if="pendingOffers.total" class="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm sm:flex-row sm:items-center dark:border-amber-500/40 dark:bg-amber-500/10">
            <p class="flex-1 text-amber-800 dark:text-amber-200">
                <Fa icon="hourglass-half" class="mr-2" />{{ t('selection.pending', { n: pendingOffers.total }) }}
                <span v-if="pendingOffers.overdue" class="ml-1 font-semibold text-red-600 dark:text-red-300">{{ t('selection.overdueCount', { n: pendingOffers.overdue }) }}</span>
            </p>
            <button v-if="canRemind" type="button" class="flex shrink-0 items-center justify-center gap-2 rounded-lg border border-amber-500 px-4 py-2 font-medium text-amber-800 transition hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-500/20" @click="remindOpen = true">
                <Fa icon="envelope" /> {{ t('selection.remind', { n: pendingOffers.total }) }}
            </button>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            <DeadlineNotice :opens-at="opensAt" :deadline="deadline" :phase="phase" />
            <!-- The first-gate deadline (Settings › Schedule): a warning only -->
            <div
                :class="firstGatePast && counts.late ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200'"
                class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
            >
                <Fa :icon="firstGatePast && counts.late ? 'triangle-exclamation' : 'calendar-days'" class="mt-0.5" />
                <div>
                    <p v-if="!journalDeadline">{{ t('selection.deadline.none') }}</p>
                    <p v-else-if="firstGatePast">{{ t(counts.late ? 'selection.deadline.past' : 'selection.deadline.pastDone', { date: formatDate(journalDeadline), n: counts.late }) }}</p>
                    <p v-else>{{ t('selection.deadline.until', { date: formatDate(journalDeadline) }) }}</p>
                    <p class="mt-1 text-xs opacity-80">{{ t('selection.answerDays', { days: answerDays }) }}</p>
                </div>
            </div>
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
                @click="pickTab(item.key)"
            >
                <Fa :icon="item.icon" /> {{ t(`selection.tabs.${item.key}`) }}
                <span :class="badge(item.key)" class="rounded-full px-2 py-0.5 text-xs font-semibold">{{ formatNumber(counts[item.key]) }}</span>
            </button>
        </nav>
        <p class="-mt-3 text-sm text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t(`selection.hint.${tab}`, { days: answerDays }) }}</p>

        <form class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_14rem_16rem_auto_auto]" @submit.prevent="load()">
            <label class="relative sm:col-span-2 xl:col-span-1">
                <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                <input v-model="q" :placeholder="t('submissions.manage.search')" :class="[input, 'pl-11']">
            </label>
            <select v-model="track" :class="input" @change="load()">
                <option value="">{{ t('submissions.manage.allTracks') }}</option>
                <option v-for="item in tracks" :key="item.id" :value="item.id">{{ tr(item.name) }}</option>
            </select>
            <select v-model="journal" :class="input" @change="load()">
                <option value="">{{ t('selection.allJournals') }}</option>
                <option v-for="item in journals" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
            <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 sm:col-span-2 xl:col-span-1">
                <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
            </button>
            <a :href="exportUrl" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm whitespace-nowrap transition hover:border-gold-400 sm:col-span-2 xl:col-span-1 dark:border-white/20">
                <Fa icon="file-excel" class="text-emerald-600 dark:text-emerald-400" /> {{ t('selection.export') }}
            </a>
        </form>

        <FirstGateTable
            :papers="papers.data"
            :show-url="showUrl"
            :deadline="deadline"
            :phase="phase"
            :tracks="tracks"
            :journals="activeJournals"
            :answer-days="answerDays"
            :empty-text="t('selection.empty')"
            :table-key="`${papers.current_page}-${papers.total}-${filters.q}-${filters.tab}-${filters.track}-${filters.journal}`"
        />

        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('submissions.manage.total', { total: formatNumber(papers.total) }) }}</p>
            <Pagination v-if="papers.last_page > 1" v-model="page" :last-page="papers.last_page" />
        </div>
    </Card>
    <BulkReminderModal
        :show="remindOpen"
        :title="t('selection.remindTitle')"
        :intro="t('selection.remindText', { n: pendingOffers.total })"
        preview-url="/admin/selection/remind-offers/preview"
        send-url="/admin/selection/remind-offers"
        :confirm-label="t('selection.remind', { n: pendingOffers.total })"
        @close="remindOpen = false"
    />
</template>
