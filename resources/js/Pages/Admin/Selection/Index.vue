<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import Pagination from '@/Components/Pagination.vue';
import FirstGateTable from '@/Components/Admin/Journal/FirstGateTable.vue';
import { useI18n } from '@/i18n';

// Selection (editors and admin): the first gate, where every paper of the year went (Journal / Conference) and the
// papers still waiting; editors pass papers on to the conference once the journal deadline has passed
const props = defineProps({
    papers: { type: Object, required: true }, // Laravel paginator
    filters: { type: Object, required: true }, // { q, tab, track }
    counts: { type: Object, required: true }, // papers per tab
    tracks: { type: Array, required: true },
    journalDeadline: { type: String, default: null },
    declineBy: { type: String, required: true }, // admin | editor
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
});

const { t, tr, formatDate, formatNumber } = useI18n();

const tabs = [
    { key: 'waiting', icon: 'inbox' },
    { key: 'late', icon: 'clock' },
    { key: 'journal', icon: 'newspaper' },
    { key: 'conference', icon: 'arrow-right' },
    { key: 'all', icon: 'list' },
];
const q = ref(props.filters.q ?? '');
const tab = ref(props.filters.tab);
const track = ref(props.filters.track ?? '');

function load(params = {}) {
    router.get('/admin/selection', { q: q.value || undefined, tab: tab.value, track: track.value || undefined, ...params }, {
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

const journalPast = computed(() => props.journalDeadline && new Date(props.journalDeadline) < new Date());
// Tabs that need someone: still waiting past the deadline shows red
const badge = (key) => (!props.counts[key] || ['all', 'journal', 'conference'].includes(key)
    ? 'bg-neutral-100 text-neutral-500 dark:bg-white/10 dark:text-neutral-400'
    : key === 'late' ? 'bg-red-500 text-white' : 'bg-gold-400 text-black');
const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
</script>

<template>
    <Head :title="t('admin.menu.selection')" />

    <Card class="space-y-6">
        <PageHeader icon="code-branch" :title="t('admin.menu.selection')" :subtitle="t('selection.subtitle')" />

        <div class="grid gap-3 lg:grid-cols-2">
            <DeadlineNotice :opens-at="opensAt" :deadline="deadline" :phase="phase" />
            <!-- The journal's deadline: after it, editors pass the papers it did not select on to the conference -->
            <div
                :class="journalPast && counts.late ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200'"
                class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
            >
                <Fa :icon="journalPast && counts.late ? 'triangle-exclamation' : 'calendar-days'" class="mt-0.5" />
                <p v-if="!journalDeadline">{{ t('selection.deadline.none') }}</p>
                <p v-else-if="journalPast">{{ t(counts.late ? 'selection.deadline.past' : 'selection.deadline.pastDone', { date: formatDate(journalDeadline), n: counts.late }) }}</p>
                <p v-else>{{ t('selection.deadline.until', { date: formatDate(journalDeadline) }) }}</p>
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
        <p class="-mt-3 text-sm text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t(`selection.hint.${tab}`) }}</p>

        <form class="grid gap-3 sm:grid-cols-[1fr_16rem_auto]" @submit.prevent="load()">
            <label class="relative">
                <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                <input v-model="q" :placeholder="t('submissions.manage.search')" :class="[input, 'pl-11']">
            </label>
            <select v-model="track" :class="input" @change="load()">
                <option value="">{{ t('submissions.manage.allTracks') }}</option>
                <option v-for="item in tracks" :key="item.id" :value="item.id">{{ tr(item.name) }}</option>
            </select>
            <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
            </button>
        </form>

        <FirstGateTable
            :papers="papers.data"
            show-url="/admin/submissions"
            :deadline="deadline"
            :phase="phase"
            :journal-deadline="journalDeadline"
            :decline-by="declineBy"
            :empty-text="t('selection.empty')"
            :table-key="`${papers.current_page}-${papers.total}-${filters.q}-${filters.tab}-${filters.track}`"
        />

        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('submissions.manage.total', { total: formatNumber(papers.total) }) }}</p>
            <Pagination v-if="papers.last_page > 1" v-model="page" :last-page="papers.last_page" />
        </div>
    </Card>
</template>
