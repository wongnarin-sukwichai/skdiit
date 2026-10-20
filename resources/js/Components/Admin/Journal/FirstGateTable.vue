<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import JournalDecisionModal from '@/Components/Admin/Journal/JournalDecisionModal.vue';
import { useI18n } from '@/i18n';

/*
 * The first-gate table shared by Journal (the journal role) and Selection (editors and admin): files to open, the
 * Journal / Conference choice (grey until made, then Journal green or Conference blue), the journal's final answer,
 * and the paper page. What each user may press comes per row (canSelect / canDecline / canResult) from the server.
 */
const props = defineProps({
    // rows: { id, code, title, authors, track, status, submittedAt, pastDeadline, gate: journal|conference|null, files, canSelect, canDecline, canResult }
    papers: { type: Array, required: true },
    showUrl: { type: String, required: true }, // paper page, e.g. /admin/journal or /admin/submissions
    deadline: { type: String, default: null }, // the submission deadline (the journal selects after it)
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
    journalDeadline: { type: String, default: null },
    declineBy: { type: String, required: true }, // admin | editor (after the journal deadline) | journal (never)
    emptyText: { type: String, required: true },
    tableKey: { type: String, default: '' }, // changes with the page or filters, for the fade
});

const { t, tr, formatDate } = useI18n();

// A decision pressed in the table: the paper and the button (select / decline / accepted / rejected)
const deciding = ref(null);
const decide = (paper, choice) => { deciding.value = { paper, choice }; };

const fileKinds = [
    { key: 'pdf', icon: 'file-pdf', tone: 'text-red-600 dark:text-red-400' },
    { key: 'word', icon: 'file-word', tone: 'text-sky-600 dark:text-sky-400' },
    { key: 'turnitin', icon: 'percent', tone: 'text-neutral-600 dark:text-neutral-300' },
];
const fileHref = (file) => (file.isPdf ? `${file.url}?inline=1` : file.url);

// Where the first selection sent the paper; papers on the journal path count as "journal" even without the event
const gateOf = (item) => item.gate ?? (['journal_selected', 'journal_accepted'].includes(item.status) ? 'journal' : null);
const pickBtn = 'w-28 rounded-md border px-2.5 py-1.5 text-center text-xs font-medium transition disabled:cursor-not-allowed';
const greyBtn = 'border-neutral-300 bg-neutral-100 text-neutral-500 dark:border-white/15 dark:bg-white/5 dark:text-neutral-400';
const journalBtn = (item) => (gateOf(item) === 'journal'
    ? 'border-emerald-500 bg-emerald-500 text-white'
    : [greyBtn, item.canSelect ? 'hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/15 dark:hover:text-emerald-300' : 'opacity-60']);
const conferenceBtn = (item) => (gateOf(item) === 'conference'
    ? 'border-sky-500 bg-sky-500 text-white'
    : [greyBtn, item.canDecline ? 'hover:border-sky-500 hover:bg-sky-50 hover:text-sky-700 dark:hover:bg-sky-500/15 dark:hover:text-sky-300' : 'opacity-60']);
// Why Conference is grey for this user (only while the paper still waits)
const declineHint = computed(() => (props.declineBy === 'editor'
    ? (props.journalDeadline ? t('journal.panel.declineEditorAfter', { date: formatDate(props.journalDeadline) }) : t('journal.panel.declineEditorNoDate'))
    : t('journal.panel.declineAdminOnly')));

const actionBtn = 'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium whitespace-nowrap transition';
const goodBtn = 'border-emerald-500 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/15';
const otherBtn = 'border-sky-500 text-sky-700 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-500/15';
const iconBtn = 'inline-grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
        <table class="w-full min-w-260 text-sm">
            <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                    <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('journal.table.files') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('journal.table.pick') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.submittedAt') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ t('journal.table.decision') }}</th>
                </tr>
            </thead>
            <Transition name="fade" mode="out-in">
                <tbody :key="tableKey" class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="item in papers" :key="item.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                        <!-- Red bar: past the journal deadline -->
                        <td :class="item.pastDeadline ? 'border-red-500' : 'border-transparent'" class="border-l-4 px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ item.code }}</td>
                        <td class="max-w-md px-4 py-3">
                            <Link :href="`${showUrl}/${item.id}`" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(item.title) }}</Link>
                            <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">{{ item.authors.map((a) => a.name).join(', ') }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ tr(item.track.name) }}</td>
                        <td class="px-4 py-3 text-center"><SubmissionStatusBadge :status="item.status" /></td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-1.5">
                                <a
                                    v-for="kind in fileKinds.filter((k) => item.files[k.key])"
                                    :key="kind.key"
                                    :href="fileHref(item.files[kind.key])"
                                    :target="item.files[kind.key].isPdf ? '_blank' : undefined"
                                    rel="noopener"
                                    :title="item.files[kind.key].name"
                                    class="inline-flex items-center gap-1 rounded-md border border-neutral-300 px-2 py-1 text-xs transition hover:border-gold-400 dark:border-white/15"
                                >
                                    <Fa :icon="kind.icon" :class="kind.tone" /> {{ t(`journal.table.file.${kind.key}`) }}
                                </a>
                            </div>
                        </td>
                        <!-- Step 1: Journal (the journal or admin) or Conference (admin, or editors after the journal deadline) -->
                        <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-1.5">
                                <button
                                    type="button"
                                    :class="[pickBtn, journalBtn(item)]"
                                    :disabled="!item.canSelect"
                                    :aria-pressed="gateOf(item) === 'journal'"
                                    :title="item.canSelect || gateOf(item) ? undefined : t('journal.panel.selectJournalOnly')"
                                    @click="decide(item, 'select')"
                                >{{ t('journal.table.journal') }}</button>
                                <button
                                    type="button"
                                    :class="[pickBtn, conferenceBtn(item)]"
                                    :disabled="!item.canDecline"
                                    :aria-pressed="gateOf(item) === 'conference'"
                                    :title="item.canDecline || gateOf(item) ? undefined : declineHint"
                                    @click="decide(item, 'decline')"
                                >{{ t('journal.table.conference') }}</button>
                                <span v-if="item.status === 'submitted' && phase !== 'closed'" class="text-xs whitespace-nowrap text-neutral-500 dark:text-neutral-400">
                                    <Fa icon="lock" class="mr-1" />{{ deadline ? t('journal.table.locked', { date: formatDate(deadline) }) : t('journal.table.lockedNoDate') }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ formatDate(item.submittedAt) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Step 2: the journal's final answer -->
                                <template v-if="item.canResult">
                                    <button type="button" :class="[actionBtn, goodBtn]" @click="decide(item, 'accepted')"><Fa icon="circle-check" /> {{ t('journal.table.accepted') }}</button>
                                    <button type="button" :class="[actionBtn, otherBtn]" @click="decide(item, 'rejected')"><Fa icon="arrow-right" /> {{ t('journal.table.rejected') }}</button>
                                </template>
                                <Link :href="`${showUrl}/${item.id}`" :class="iconBtn" :title="t('registrants.view')"><Fa icon="eye" class="text-xs" /></Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!papers.length">
                        <td colspan="8" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">{{ emptyText }}</td>
                    </tr>
                </tbody>
            </Transition>
        </table>
    </div>

    <JournalDecisionModal :paper="deciding?.paper" :choice="deciding?.choice" @close="deciding = null" />
</template>
