<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import Pagination from '@/Components/Pagination.vue';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import WaitingOnBadge from '@/Components/Admin/WaitingOnBadge.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    submissions: { type: Object, required: true }, // Laravel paginator
    filters: { type: Object, required: true },
    tracks: { type: Array, required: true },
    statuses: { type: Array, required: true },
    cameraReadyCount: { type: Number, required: true },
    queues: { type: Object, required: true }, // papers per editor work queue (Submission::queueCounts())
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
});

const { t, tr, formatDate, formatNumber } = useI18n();

const q = ref(props.filters.q ?? '');
const track = ref(props.filters.track ?? '');
const status = ref(props.filters.status ?? '');
const queue = ref(props.filters.queue ?? '');

// Work queues first, so editors see what needs them; "All" keeps the status filter
const queueTabs = [
    { key: '', icon: 'list' },
    { key: 'screening', icon: 'file-lines' },
    { key: 'needReviewers', icon: 'user-check' },
    { key: 'readyToDecide', icon: 'gavel' },
    { key: 'revised', icon: 'rotate' },
    { key: 'overdue', icon: 'triangle-exclamation' },
    { key: 'unavailable', icon: 'user-xmark' },
];
function pickQueue(key) {
    queue.value = key;
    if (key) status.value = '';
    load();
}

function load(params = {}) {
    router.get('/admin/submissions', { q: q.value || undefined, track: track.value || undefined, status: status.value || undefined, queue: queue.value || undefined, ...params }, {
        only: ['submissions', 'filters', 'queues'],
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
    queue.value = '';
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
                href="/admin/submissions/export/camera-ready"
                :class="cameraReadyCount ? 'hover:border-gold-400 hover:text-gold-600 dark:hover:text-gold-400' : 'pointer-events-none opacity-50'"
                class="flex shrink-0 items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition dark:border-white/20"
                :aria-disabled="!cameraReadyCount"
            >
                <Fa icon="file-zipper" class="text-gold-600 dark:text-gold-400" /> {{ t('publication.zip', { count: formatNumber(cameraReadyCount) }) }}
            </a>
        </div>

        <nav class="flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-white/10" role="tablist">
            <button
                v-for="item in queueTabs"
                :key="item.key || 'all'"
                role="tab"
                :aria-selected="queue === item.key"
                :class="queue === item.key
                    ? 'border-gold-400 text-gold-600 dark:text-gold-400'
                    : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                @click="pickQueue(item.key)"
            >
                <Fa :icon="item.icon" :class="item.key === 'overdue' && queues.overdue ? 'text-red-500' : ''" />
                {{ t(`submissions.queue.${item.key || 'all'}`) }}
                <span
                    v-if="item.key"
                    :class="queues[item.key] ? (item.key === 'overdue' ? 'bg-red-500 text-white' : 'bg-gold-400 text-black') : 'bg-neutral-100 text-neutral-500 dark:bg-white/10 dark:text-neutral-400'"
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                >{{ formatNumber(queues[item.key]) }}</span>
            </button>
        </nav>
        <p v-if="queue" class="-mt-3 text-sm text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t(`submissions.queue.hint.${queue}`) }}</p>

        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_14rem_14rem_auto_auto]" @submit.prevent="load()">
            <label class="relative">
                <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                <input v-model="q" :placeholder="t('submissions.manage.search')" :class="[input, 'pl-11']">
            </label>
            <select v-model="track" :class="input" @change="load()">
                <option value="">{{ t('submissions.manage.allTracks') }}</option>
                <option v-for="item in tracks" :key="item.id" :value="String(item.id)">{{ tr(item.name) }}</option>
            </select>
            <select v-model="status" :class="input" :disabled="!!queue" :title="queue ? t('submissions.queue.statusLocked') : undefined" @change="load()">
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

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-240 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.waiting.column') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.submittedAt') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <Transition name="fade" mode="out-in">
                    <tbody :key="`${submissions.current_page}-${submissions.total}-${filters.q}-${filters.track}-${filters.status}-${filters.queue}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="item in submissions.data" :key="item.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                            <!-- Gold bar on the left: the editors' move -->
                            <td :class="item.waitingOn.who === 'editor' ? 'border-gold-400' : 'border-transparent'" class="border-l-4 px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ item.code }}</td>
                            <td class="max-w-md px-4 py-3">
                                <Link :href="`/admin/submissions/${item.id}`" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(item.title) }}</Link>
                                <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">
                                    <span v-if="item.notes" class="mr-1.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300" :title="t('notes.count', { n: item.notes })"><Fa icon="note-sticky" class="text-[0.65rem]" />{{ item.notes }}</span>{{ item.authors.map((a) => a.name).join(', ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ tr(item.track.name) }}</td>
                            <td class="px-4 py-3 text-center"><SubmissionStatusBadge :status="item.status" /></td>
                            <!-- Whose move it is: editor, reviewer (with x/y sent) or author -->
                            <td class="px-4 py-3"><WaitingOnBadge :waiting="item.waitingOn" /></td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ formatDate(item.submittedAt) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/admin/submissions/${item.id}`" :class="[iconBtn, 'inline-grid']" :title="t('registrants.view')"><Fa icon="eye" class="text-xs" /></Link>
                            </td>
                        </tr>
                        <tr v-if="!submissions.data.length">
                            <td colspan="7" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">{{ t(queue ? 'submissions.queue.empty' : 'submissions.manage.empty') }}</td>
                        </tr>
                    </tbody>
                </Transition>
            </table>
        </div>

        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('submissions.manage.total', { total: formatNumber(submissions.total) }) }}</p>
            <Pagination v-if="submissions.last_page > 1" v-model="page" :last-page="submissions.last_page" />
        </div>
    </Card>
</template>
