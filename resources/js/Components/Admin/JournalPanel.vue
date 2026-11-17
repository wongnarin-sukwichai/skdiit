<script setup>
import { computed, ref } from 'vue';
import JournalDecisionModal from '@/Components/Admin/Journal/JournalDecisionModal.vue';
import { useI18n } from '@/i18n';

/*
 * The first gate on a paper page (SelectionController::panel, 2026-11-11): the journal offered and the author's
 * answer, the decisions so far with the staff's notes (never shown to the author), and the Journal / Conference
 * buttons for admin, editors and the journal role while the gate is open.
 */
const props = defineProps({
    submission: { type: Object, required: true }, // { id, code, title, status, track, journal, journalAnswerDue }
    journal: { type: Object, required: true }, // { canRoute, tracks, journals, answerDays, deadline, pastDeadline, decisions }
    deadlinePassed: { type: Boolean, required: true }, // the submission deadline (the first gate opens after it)
});

const { t, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const status = computed(() => props.submission.status);
// Papers at the first gate, or that went through it
const visible = computed(() => ['submitted', 'journal_selected', 'journal_accepted'].includes(status.value) || props.journal.decisions.length);

const deciding = ref(null); // journal | conference

// History rows: the first-gate decision (with the journal's name) or the author's answer
function decisionTitle(item) {
    if (item.action === 'journal_offer_answered') return t(`journal.decision.answered.${item.meta?.answer ?? 'decline'}`, { journal: item.meta?.journal ?? '' });
    if (item.action === 'journal_screened' && item.toStatus === 'journal_selected') return t('journal.decision.offered', { journal: item.meta?.journal ?? '' });
    return t(`journal.decision.${item.action}.${item.toStatus}`);
}

const btn = 'flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition';
</script>

<template>
    <section v-if="visible" class="space-y-4 rounded-xl border border-emerald-300 p-4 dark:border-emerald-400/30">
        <h2 class="flex items-center gap-2 font-semibold"><Fa icon="code-branch" class="text-emerald-600 dark:text-emerald-400" /> {{ t('journal.panel.title') }}</h2>

        <p v-if="journal.pastDeadline" class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
            <Fa icon="triangle-exclamation" class="mt-0.5" /> {{ t('journal.panel.pastDeadline', { date: formatDate(journal.deadline) }) }}
        </p>

        <!-- Where it stands now -->
        <div v-if="submission.journal && ['journal_selected', 'journal_accepted'].includes(status)" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm dark:bg-emerald-400/10">
            <p class="font-medium">{{ submission.journal.name }}</p>
            <p v-if="status === 'journal_selected'" class="text-amber-700 dark:text-amber-300">
                <Fa icon="hourglass-half" class="mr-1" />{{ t('journal.panel.waitingAnswer', { date: formatDate(submission.journalAnswerDue) }) }}
            </p>
            <p v-else class="text-emerald-700 dark:text-emerald-300"><Fa icon="circle-check" class="mr-1" />{{ t('journal.panel.acceptedNote') }}</p>
            <a v-if="submission.journal.url" :href="$url(submission.journal.url)" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-xs text-sky-700 hover:underline dark:text-sky-300">
                {{ submission.journal.url }} <Fa icon="arrow-up-right-from-square" class="text-[0.6rem]" />
            </a>
        </div>

        <!-- Decisions and answers so far, with the staff's notes -->
        <ul v-if="journal.decisions.length" class="space-y-2">
            <li v-for="item in journal.decisions" :key="item.id" class="rounded-lg bg-neutral-50 px-3 py-2 text-sm dark:bg-white/5">
                <p class="font-medium">{{ decisionTitle(item) }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ item.actor?.name ?? t('journal.panel.system') }} · {{ when(item.at) }}</p>
                <p v-if="item.comment" class="mt-1 whitespace-pre-line text-neutral-700 dark:text-neutral-200">{{ item.comment }}</p>
            </li>
        </ul>

        <!-- The decision, while the gate is open -->
        <div v-if="journal.canRoute" class="space-y-2">
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ t(status === 'journal_selected' ? 'journal.panel.changeIntro' : 'journal.panel.selectIntro') }}</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <button type="button" :class="btn" class="border-emerald-500 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/15" @click="deciding = 'journal'">
                    <Fa icon="newspaper" /> {{ t(status === 'journal_selected' ? 'journal.choice.otherJournal' : 'journal.choice.journal') }}
                </button>
                <button type="button" :class="btn" class="border-sky-500 text-sky-700 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-500/15" @click="deciding = 'conference'">
                    <Fa icon="arrow-right" /> {{ t('journal.choice.conference') }}
                </button>
            </div>
        </div>
        <p v-else-if="status === 'submitted' && !deadlinePassed" class="text-sm text-neutral-500 dark:text-neutral-400">
            <Fa icon="lock" class="mr-1" /> {{ t('journal.panel.afterDeadline') }}
        </p>

        <JournalDecisionModal
            :paper="deciding ? submission : null"
            :choice="deciding"
            :tracks="journal.tracks"
            :journals="journal.journals"
            :answer-days="journal.answerDays"
            @close="deciding = null"
        />
    </section>
</template>
