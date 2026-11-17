<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import Pagination from '@/Components/Pagination.vue';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import RevisionBadge from '@/Components/Admin/RevisionBadge.vue';
import RemindAuthorButton from '@/Components/Admin/RemindAuthorButton.vue';
import WaitingOnBadge from '@/Components/Admin/WaitingOnBadge.vue';
import ReviewerStatusList from '@/Components/Admin/ReviewerStatusList.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    submissions: { type: Object, required: true }, // Laravel paginator
    filters: { type: Object, required: true },
    tracks: { type: Array, required: true },
    statuses: { type: Array, required: true }, // conference-track statuses (first-gate papers live on Selection)
    atFirstGate: { type: Number, default: 0 }, // papers matching the search that are still at the first gate
    cameraReadyCount: { type: Number, required: true },
    stages: { type: Object, required: true }, // per tab: { count: waiting for the editors, total } (Submission::stageCounts())
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
});

const { t, tr, formatDate, formatNumber } = useI18n();

const q = ref(props.filters.q ?? '');
const track = ref(props.filters.track ?? '');
const status = ref(props.filters.status ?? '');
const stage = ref(props.filters.stage ?? 'all');
const inStage = computed(() => stage.value && stage.value !== 'all'); // a stage tab, not "all"

// Where each paper stands (2026-11-12), one tab per paper; "All" (last, 2026-11-13) keeps the status filter and
// rejected papers. Opening the menu shows the first tab with the editors' move (the server picks it)
const stageTabs = [
    { key: 'screening', icon: 'file-lines' },
    { key: 'revisedScreening', icon: 'rotate' },
    { key: 'findReviewers', icon: 'user-check' },
    { key: 'reviewing', icon: 'clipboard-check' },
    { key: 'decide', icon: 'gavel' },
    { key: 'awaitingFinal', icon: 'hourglass-half' },
    { key: 'cameraReady', icon: 'file-circle-check' },
    { key: 'all', icon: 'list' },
];
function pickStage(key) {
    stage.value = key;
    if (key !== 'all') status.value = '';
    load();
}
// Gold: papers waiting for the editors; grey: papers in the tab that wait for someone else
const tabNumber = (key) => (props.stages[key].count || props.stages[key].total);
const tabTone = (key) => (props.stages[key].count ? 'bg-gold-400 text-black' : 'bg-neutral-100 text-neutral-500 dark:bg-white/10 dark:text-neutral-400');

// List (paged table) or cards grouped by track (every matching paper); the choice is remembered on this browser
const VIEW_KEY = 'skdiit.submissions.view';
const readView = () => { try { return localStorage.getItem(VIEW_KEY); } catch { return null; } };
const view = ref(props.filters.view ?? (readView() === 'tracks' ? 'tracks' : 'list'));
function pickView(value) {
    view.value = value;
    try { localStorage.setItem(VIEW_KEY, value); } catch { /* private mode: not remembered */ }
    load();
}
onMounted(() => {
    if (view.value === 'tracks' && props.filters.view !== 'tracks') load();
});
// Tracks in their usual order, each with its papers (tracks without a matching paper are left out)
const byTrack = computed(() => props.tracks
    .map((item) => ({ track: item, papers: props.submissions.data.filter((p) => p.track?.id === item.id) }))
    .filter((group) => group.papers.length));

function load(params = {}) {
    router.get('/admin/submissions', { q: q.value || undefined, track: track.value || undefined, status: status.value || undefined, stage: stage.value || 'all', view: view.value === 'tracks' ? 'tracks' : undefined, ...params }, {
        only: ['submissions', 'filters', 'stages'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
const page = computed({
    get: () => props.submissions.current_page,
    set: (value) => load({ page: value }),
});
function reset() {
    q.value = '';
    track.value = '';
    status.value = '';
    stage.value = 'all';
    load();
}

const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <Head :title="t('admin.menu.submissions')" />

    <Card class="space-y-6">
        <PageHeader icon="file-lines" :title="t('admin.menu.submissions')" :subtitle="t('submissions.manage.subtitle')" />
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <DeadlineNotice :opens-at="opensAt" :deadline="deadline" :phase="phase" class="flex-1" />
            <!-- Every camera-ready file in one ZIP, for compiling the Proceeding -->
            <a
                :href="$url('/admin/submissions/export/camera-ready')"
                :class="cameraReadyCount ? 'hover:border-gold-400 hover:text-gold-600 dark:hover:text-gold-400' : 'pointer-events-none opacity-50'"
                class="flex shrink-0 items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition dark:border-white/20"
                :aria-disabled="!cameraReadyCount"
            >
                <Fa icon="file-zipper" class="text-gold-600 dark:text-gold-400" /> {{ t('publication.zip', { count: formatNumber(cameraReadyCount) }) }}
            </a>
        </div>

        <nav class="flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-white/10" role="tablist">
            <button
                v-for="item in stageTabs"
                :key="item.key"
                role="tab"
                :aria-selected="stage === item.key"
                :class="stage === item.key
                    ? 'border-gold-400 text-gold-600 dark:text-gold-400'
                    : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                @click="pickStage(item.key)"
            >
                <Fa :icon="item.icon" />
                {{ t(`submissions.stage.${item.key}`) }}
                <span v-if="item.key !== 'all'" :class="tabTone(item.key)" class="rounded-full px-2 py-0.5 text-xs font-semibold">{{ formatNumber(tabNumber(item.key)) }}</span>
            </button>
        </nav>
        <p v-if="inStage" class="-mt-3 text-sm text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t(`submissions.stage.hint.${stage}`) }}</p>

        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_14rem_14rem_auto_auto]" @submit.prevent="load()">
            <label class="relative">
                <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                <input v-model="q" :placeholder="t('submissions.manage.search')" :class="[input, 'pl-11']">
            </label>
            <select v-model="track" :class="input" @change="load()">
                <option value="">{{ t('submissions.manage.allTracks') }}</option>
                <option v-for="item in tracks" :key="item.id" :value="String(item.id)">{{ tr(item.name) }}</option>
            </select>
            <select v-model="status" :class="input" :disabled="inStage" :title="inStage ? t('submissions.queue.statusLocked') : undefined" @change="load()">
                <option value="">{{ t('submissions.manage.allStatuses') }}</option>
                <option v-for="item in statuses" :key="item" :value="item">{{ t(`submissions.status.${item}`) }}</option>
            </select>
            <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
            </button>
            <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20" @click="reset">
                <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
            </button>
        </form>

        <!-- The search also matches papers still at the first gate: they are on Selection -->
        <Transition name="fade">
            <p v-if="atFirstGate && filters.q" class="flex flex-wrap items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                <Fa icon="code-branch" /> {{ t('submissions.manage.atFirstGate', { n: formatNumber(atFirstGate) }) }}
                <Link :href="$url(`/admin/selection?tab=all&q=${encodeURIComponent(filters.q)}`)" class="font-semibold underline">{{ t('submissions.manage.openSelection') }}</Link>
            </p>
        </Transition>

        <!-- List or cards by track -->
        <div class="flex justify-end">
            <div class="flex rounded-lg border border-neutral-200 p-0.5 text-xs dark:border-white/10" role="group">
                <button
                    v-for="mode in [{ key: 'list', icon: 'list' }, { key: 'tracks', icon: 'layer-group' }]"
                    :key="mode.key"
                    type="button"
                    :aria-pressed="view === mode.key"
                    :class="view === mode.key ? 'bg-gold-400 text-black' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition"
                    @click="pickView(mode.key)"
                >
                    <Fa :icon="mode.icon" /> {{ t(`submissions.manage.view.${mode.key}`) }}
                </button>
            </div>
        </div>

        <Transition name="fade" mode="out-in">
        <div v-if="view === 'list'" key="list" class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-280 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.reviewersColumn') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.waiting.column') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.submittedAt') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <Transition name="fade" mode="out-in">
                    <tbody :key="`${submissions.current_page}-${submissions.total}-${filters.q}-${filters.track}-${filters.status}-${filters.stage}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="item in submissions.data" :key="item.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                            <!-- Gold bar on the left: the editors' move -->
                            <td :class="item.waitingOn.who === 'editor' ? 'border-gold-400' : 'border-transparent'" class="border-l-4 px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ item.code }}</td>
                            <td class="max-w-md px-4 py-3">
                                <Link :href="$url(`/admin/submissions/${item.id}`)" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(item.title) }}</Link>
                                <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">
                                    <span v-if="item.notes" class="mr-1.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300" :title="t('notes.count', { n: item.notes })"><Fa icon="note-sticky" class="text-[0.65rem]" />{{ item.notes }}</span>{{ item.authors.map((a) => a.name).join(', ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ item.track ? tr(item.track.name) : t('submissions.noTrack') }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex flex-col items-center gap-1">
                                    <SubmissionStatusBadge :status="item.status" />
                                    <RevisionBadge :status="item.status" :revision-count="item.revisionCount" :due-date="item.revisionDueDate" :camera-ready-approved="item.cameraReadyApproved" :camera-ready-fix="item.cameraReadyFix" />
                                </span>
                            </td>
                            <!-- Whose move it is: editor, reviewer (with x/y sent) or author -->
                            <!-- This round's reviewers: who has sent (and what), who is still reviewing -->
                            <td class="px-4 py-3"><ReviewerStatusList :reviewers="item.reviewers" /></td>
                            <td class="px-4 py-3">
                                <span class="flex flex-col items-start gap-1.5">
                                    <WaitingOnBadge :waiting="item.waitingOn" />
                                    <RemindAuthorButton :submission="item" compact />
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ formatDate(item.submittedAt) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Link :href="$url(`/admin/submissions/${item.id}`)" :class="[iconBtn, 'inline-grid']" :title="t('registrants.view')"><Fa icon="eye" class="text-xs" /></Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!submissions.data.length">
                            <td colspan="8" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">{{ t(inStage ? 'submissions.queue.empty' : 'submissions.manage.empty') }}</td>
                        </tr>
                    </tbody>
                </Transition>
            </table>
        </div>

        <!-- Cards by track: every matching paper, its status and whose move it is -->
        <div v-else key="tracks" class="space-y-6">
            <p v-if="!byTrack.length" class="rounded-xl border border-dashed border-neutral-300 py-12 text-center text-sm text-neutral-500 dark:border-white/15 dark:text-neutral-400">{{ t(inStage ? 'submissions.queue.empty' : 'submissions.manage.empty') }}</p>
            <section v-for="group in byTrack" :key="group.track.id">
                <h3 class="mb-3 flex items-center gap-2 font-semibold">
                    <Fa icon="layer-group" class="text-gold-500" /> {{ tr(group.track.name) }}
                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-semibold text-neutral-600 dark:bg-white/10 dark:text-neutral-300">{{ formatNumber(group.papers.length) }}</span>
                </h3>
                <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="item in group.papers" :key="item.id">
                        <Link
                            :href="$url(`/admin/submissions/${item.id}`)"
                            :class="item.waitingOn.who === 'editor' ? 'border-l-gold-400' : 'border-l-neutral-200 dark:border-l-white/10'"
                            class="flex h-full flex-col gap-2 rounded-xl border border-l-4 border-neutral-200 p-3 transition hover:border-gold-400 hover:bg-gold-50/50 dark:border-white/10 dark:hover:bg-gold-400/5"
                        >
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-mono text-xs text-gold-600 dark:text-gold-400">{{ item.code }}</span>
                                <span v-if="item.notes" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 text-xs text-amber-700 dark:bg-amber-400/15 dark:text-amber-300" :title="t('notes.count', { n: item.notes })"><Fa icon="note-sticky" class="text-[0.65rem]" />{{ item.notes }}</span>
                            </span>
                            <span class="line-clamp-2 text-sm font-medium">{{ tr(item.title) }}</span>
                            <span class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ item.authors.map((a) => a.name).join(', ') }}</span>
                            <span class="mt-auto flex flex-wrap items-center gap-2 pt-1">
                                <SubmissionStatusBadge :status="item.status" />
                                <RevisionBadge :status="item.status" :revision-count="item.revisionCount" :due-date="item.revisionDueDate" :camera-ready-approved="item.cameraReadyApproved" :camera-ready-fix="item.cameraReadyFix" />
                                <WaitingOnBadge :waiting="item.waitingOn" />
                                <RemindAuthorButton :submission="item" compact />
                            </span>
                            <ReviewerStatusList v-if="item.reviewers.length" :reviewers="item.reviewers" class="border-t border-neutral-200 pt-2 dark:border-white/10" />
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
        </Transition>

        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('submissions.manage.total', { total: formatNumber(submissions.total) }) }}</p>
            <Pagination v-if="submissions.last_page > 1" v-model="page" :last-page="submissions.last_page" />
        </div>
    </Card>
</template>
