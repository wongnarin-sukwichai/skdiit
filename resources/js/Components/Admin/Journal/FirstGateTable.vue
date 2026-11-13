<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import JournalDecisionModal from '@/Components/Admin/Journal/JournalDecisionModal.vue';
import { useI18n } from '@/i18n';
import RemindAuthorButton from '@/Components/Admin/RemindAuthorButton.vue';

/*
 * The first-gate table of Selection (admin, editors and the journal role alike, 2026-11-11): files to open, the
 * journal offered and the author's answer, and the Journal / Conference choice (grey until made, then Journal green
 * or Conference blue). While an offer is unanswered both buttons stay open: pick another journal, or the conference.
 */
const props = defineProps({
    // rows: { id, code, title, authors, track, status, submittedAt, pastDeadline, gate, journal, journalAnswerDue, declinedJournal, files, canRoute, canSetTrack }
    papers: { type: Array, required: true },
    showUrl: { type: String, required: true }, // paper page: /admin/submissions (editors, admin) or /admin/selection (journal role)
    deadline: { type: String, default: null }, // the submission deadline (the first gate opens after it)
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
    tracks: { type: Array, required: true },
    journals: { type: Array, required: true }, // active partner journals
    answerDays: { type: Number, required: true },
    emptyText: { type: String, required: true },
    tableKey: { type: String, default: '' }, // changes with the page or filters, for the fade
});

const { t, tr, formatDate } = useI18n();

// Editors and admin put a paper in a track straight from the list (also before submissions close); saved on change
const savingTrack = ref(null);
function setTrack(paper, trackId) {
    savingTrack.value = paper.id;
    router.put(`/admin/selection/${paper.id}/track`, { track_id: Number(trackId) }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { savingTrack.value = null; },
    });
}

// A decision pressed in the table: the paper and the button (journal / conference)
const deciding = ref(null);
const decide = (paper, choice) => { deciding.value = { paper, choice }; };

const fileKinds = [
    { key: 'pdf', icon: 'file-pdf', tone: 'text-red-600 dark:text-red-400' },
    { key: 'word', icon: 'file-word', tone: 'text-sky-600 dark:text-sky-400' },
    { key: 'turnitin', icon: 'percent', tone: 'text-neutral-600 dark:text-neutral-300' },
];
const fileHref = (file) => (file.isPdf ? `${file.url}?inline=1` : file.url);

const pickBtn = 'w-28 rounded-md border px-2.5 py-1.5 text-center text-xs font-medium transition disabled:cursor-not-allowed';
const greyBtn = 'border-neutral-300 bg-neutral-100 text-neutral-500 dark:border-white/15 dark:bg-white/5 dark:text-neutral-400';
const journalBtn = (item) => (item.gate === 'journal'
    ? ['border-emerald-500 bg-emerald-500 text-white', item.canRoute ? 'hover:bg-emerald-600' : '']
    : [greyBtn, item.canRoute ? 'hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/15 dark:hover:text-emerald-300' : 'opacity-60']);
const conferenceBtn = (item) => (item.gate === 'conference'
    ? 'border-sky-500 bg-sky-500 text-white'
    : [greyBtn, item.canRoute ? 'hover:border-sky-500 hover:bg-sky-50 hover:text-sky-700 dark:hover:bg-sky-500/15 dark:hover:text-sky-300' : 'opacity-60']);

const iconBtn = 'inline-grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
        <table class="w-full min-w-280 text-sm">
            <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                    <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('journal.table.journalName') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('journal.table.files') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('journal.table.pick') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('submissions.submittedAt') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                </tr>
            </thead>
            <Transition name="fade" mode="out-in">
                <tbody :key="tableKey" class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="item in papers" :key="item.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                        <!-- Red bar: still waiting past the first-gate deadline -->
                        <td :class="item.pastDeadline ? 'border-red-500' : 'border-transparent'" class="border-l-4 px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ item.code }}</td>
                        <td class="max-w-md px-4 py-3">
                            <Link :href="`${showUrl}/${item.id}`" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(item.title) }}</Link>
                            <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">{{ item.authors.map((a) => a.name).join(', ') }}</span>
                        </td>
                        <td class="px-4 py-3 text-neutral-600 dark:text-neutral-300">
                            <select
                                v-if="item.canSetTrack"
                                :value="item.track?.id ?? ''"
                                :disabled="savingTrack === item.id"
                                :aria-label="t('submissions.fields.track')"
                                :class="item.track ? 'border-neutral-300 dark:border-white/15' : 'border-amber-400 text-amber-700 dark:border-amber-400/60 dark:text-amber-300'"
                                class="w-44 rounded-md border bg-white px-2 py-1.5 text-xs outline-none transition focus:border-gold-400 disabled:opacity-60 dark:bg-ink-800"
                                @change="setTrack(item, $event.target.value)"
                            >
                                <option value="" disabled>{{ t('submissions.form.chooseTrack') }}</option>
                                <option v-for="track in tracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                            </select>
                            <span v-else-if="item.track" class="whitespace-nowrap">{{ tr(item.track.name) }}</span>
                            <span v-else class="whitespace-nowrap text-neutral-400">{{ t('submissions.noTrack') }}</span>
                        </td>
                        <td class="px-4 py-3 text-center"><SubmissionStatusBadge :status="item.status" /></td>
                        <!-- The journal offered, and where the author's answer stands -->
                        <td class="max-w-56 px-4 py-3">
                            <template v-if="item.journal">
                                <span class="line-clamp-2 text-xs font-medium">{{ item.journal.name }}</span>
                                <span v-if="item.status === 'journal_selected' && item.journalOfferOverdue" class="mt-0.5 block text-xs font-semibold text-red-600 dark:text-red-400">
                                    <Fa icon="triangle-exclamation" class="mr-1" />{{ t('journal.table.answerOverdue', { date: formatDate(item.journalAnswerDue) }) }}
                                </span>
                                <span v-else-if="item.status === 'journal_selected' && item.journalAnswerDue" class="mt-0.5 block text-xs text-amber-600 dark:text-amber-300">
                                    <Fa icon="hourglass-half" class="mr-1" />{{ t('journal.table.answerBy', { date: formatDate(item.journalAnswerDue) }) }}
                                </span>
                                <RemindAuthorButton v-if="item.status === 'journal_selected'" :submission="item" compact class="mt-1" />
                                <span v-else-if="item.status === 'journal_accepted'" class="mt-0.5 block text-xs text-emerald-600 dark:text-emerald-300">
                                    <Fa icon="circle-check" class="mr-1" />{{ t('journal.table.authorAccepted') }}
                                </span>
                            </template>
                            <span v-else-if="item.declinedJournal" class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="arrow-right" class="mr-1" />{{ t('journal.table.authorDeclined') }}</span>
                            <span v-else class="text-neutral-400">—</span>
                        </td>
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
                        <!-- Journal (pick one) or Conference; open after the submission deadline until the author answers -->
                        <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-1.5">
                                <button
                                    type="button"
                                    :class="[pickBtn, journalBtn(item)]"
                                    :disabled="!item.canRoute"
                                    :aria-pressed="item.gate === 'journal'"
                                    :title="item.canRoute && item.gate === 'journal' ? t('journal.table.changeJournal') : undefined"
                                    @click="decide(item, 'journal')"
                                >{{ t('journal.table.journal') }}</button>
                                <button
                                    type="button"
                                    :class="[pickBtn, conferenceBtn(item)]"
                                    :disabled="!item.canRoute"
                                    :aria-pressed="item.gate === 'conference'"
                                    @click="decide(item, 'conference')"
                                >{{ t('journal.table.conference') }}</button>
                                <span v-if="item.status === 'submitted' && phase !== 'closed'" class="text-xs whitespace-nowrap text-neutral-500 dark:text-neutral-400">
                                    <Fa icon="lock" class="mr-1" />{{ deadline ? t('journal.table.locked', { date: formatDate(deadline) }) : t('journal.table.lockedNoDate') }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ formatDate(item.submittedAt) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`${showUrl}/${item.id}`" :class="iconBtn" :title="t('registrants.view')"><Fa icon="eye" class="text-xs" /></Link>
                        </td>
                    </tr>
                    <tr v-if="!papers.length">
                        <td colspan="9" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">{{ emptyText }}</td>
                    </tr>
                </tbody>
            </Transition>
        </table>
    </div>

    <JournalDecisionModal
        :paper="deciding?.paper"
        :choice="deciding?.choice"
        :tracks="tracks"
        :journals="journals"
        :answer-days="answerDays"
        @close="deciding = null"
    />
</template>
