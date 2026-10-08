<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import Pagination from '@/Components/Pagination.vue';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    submissions: { type: Object, required: true }, // Laravel paginator
    filters: { type: Object, required: true },
    tracks: { type: Array, required: true },
    statuses: { type: Array, required: true },
    optionBCount: { type: Number, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
});

const { t, tr, formatDate, formatNumber } = useI18n();

const q = ref(props.filters.q ?? '');
const track = ref(props.filters.track ?? '');
const status = ref(props.filters.status ?? '');

function load(params = {}) {
    router.get('/admin/submissions', { q: q.value || undefined, track: track.value || undefined, status: status.value || undefined, ...params }, {
        only: ['submissions', 'filters'],
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
            <DeadlineNotice :deadline="deadline" :is-open="isOpen" class="flex-1" />
            <!-- Option B papers for the editors to send to journals (a plain link: it is a file download) -->
            <a
                href="/admin/submissions/export/option-b"
                :class="optionBCount ? 'hover:border-emerald-500 hover:text-emerald-600 dark:hover:text-emerald-400' : 'pointer-events-none opacity-50'"
                class="flex shrink-0 items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition dark:border-white/20"
                :aria-disabled="!optionBCount"
            >
                <Fa icon="file-excel" class="text-emerald-600 dark:text-emerald-400" /> {{ t('publication.export', { count: formatNumber(optionBCount) }) }}
            </a>
        </div>

        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_14rem_14rem_auto_auto]" @submit.prevent="load()">
            <label class="relative">
                <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                <input v-model="q" :placeholder="t('submissions.manage.search')" :class="[input, 'pl-11']">
            </label>
            <select v-model="track" :class="input" @change="load()">
                <option value="">{{ t('submissions.manage.allTracks') }}</option>
                <option v-for="item in tracks" :key="item.id" :value="String(item.id)">{{ tr(item.name) }}</option>
            </select>
            <select v-model="status" :class="input" @change="load()">
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
            <table class="w-full min-w-[860px] text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.submittedAt') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <Transition name="fade" mode="out-in">
                    <tbody :key="`${submissions.current_page}-${submissions.total}-${filters.q}-${filters.track}-${filters.status}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="item in submissions.data" :key="item.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                            <td class="px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ item.code }}</td>
                            <td class="max-w-md px-4 py-3">
                                <Link :href="`/admin/submissions/${item.id}`" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(item.title) }}</Link>
                                <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">{{ item.authors.map((a) => a.name).join(', ') }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ tr(item.track.name) }}</td>
                            <td class="px-4 py-3 text-center"><SubmissionStatusBadge :status="item.status" /></td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ formatDate(item.submittedAt) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/admin/submissions/${item.id}`" :class="[iconBtn, 'inline-grid']" :title="t('registrants.view')"><Fa icon="eye" class="text-xs" /></Link>
                            </td>
                        </tr>
                        <tr v-if="!submissions.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">{{ t('submissions.manage.empty') }}</td>
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
