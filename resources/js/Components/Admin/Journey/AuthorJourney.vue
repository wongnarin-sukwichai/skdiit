<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';
import { REJECTED, REVISING, journeySteps } from '@/utils/journey';
import { toDay, today as todayIn } from '@/utils/timeline';

/*
 * A paper's path as its author sees it: up to six steps in the author's own words (the first gate, screening, review and
 * decisions are one "under consideration" step; the journal is named once a paper is offered to it), and one card
 * that says whose turn it is: the author's task with its due date and a button, or what the organisers are doing.
 * A journal offer (2026-11-11) is answered on the card: accept (go to the journal) or decline (conference track).
 * Steps run across on a wide box and down on a narrow one (container query, so the preview can show both).
 */
const props = defineProps({
    // { status, submittedAt, revisionDueDate, journal?: { name, url, terms }, journalAnswerDue,
    //   publication?: { cameraReady, presentation: { at, room }, slides, slidesUntil, canUploadSlides } }
    submission: { type: Object, required: true },
    window: { type: Object, required: true }, // { deadline, phase: upcoming|open|closed } (Submission::window())
    dates: { type: Object, default: () => ({}) }, // public schedule: { notify, cameraReady, conference } (Y-m-d)
    hadRevision: { type: Boolean, default: false }, // the author sent a revision before (shows the revision step as done)
    message: { type: String, default: null }, // the editor's latest decision message, shown with a revision, acceptance or rejection
    messageFile: { type: Object, default: null }, // { name, url } the editor attached to that decision
    links: { type: Object, default: () => ({}) }, // { edit, answer } (answer = where the journal offer is answered; none in the preview)
    today: { type: String, default: () => todayIn() },
    // full: steps + the whose-turn card; row: one line (step dots with the current step, whose turn, the card title)
    layout: { type: String, default: 'full' },
});

const { t, formatDate } = useI18n();

const s = computed(() => props.submission.status);
const pub = computed(() => props.submission.publication ?? {});
const presentingStatus = computed(() => (s.value === 'camera_ready' && props.submission.cameraReadyApproved) || s.value === 'journal_accepted');
const isRejected = computed(() => REJECTED.includes(s.value));
const isRevising = computed(() => REVISING.includes(s.value));
const stillEditable = computed(() => s.value === 'submitted' && props.window.phase !== 'closed');

const date = (value) => (value ? formatDate(value.slice(0, 10)) : null);
const note = (key, params) => t(`submissions.journey.note.${key}`, params);

// The line under each step: a date the author can plan by, or where it stands
function noteFor({ key, state }) {
    const p = props;
    switch (key) {
        case 'submit': return state === 'current' && p.window.deadline ? note('editUntil', { date: date(p.window.deadline) }) : date(p.submission.submittedAt);
        case 'screening': return state === 'upcoming' && p.window.deadline ? note('afterClose', { date: date(p.window.deadline) }) : (state === 'current' && !REVISING.includes(s.value) ? note('inProgress') : null);
        case 'peerReview': return state === 'current' && s.value === 'review_revision' && p.submission.revisionDueDate ? note('within', { date: date(p.submission.revisionDueDate) }) : (state === 'current' && s.value === 'in_review' ? note('inProgress') : null);
        case 'journal': return state === 'current' && p.submission.journalAnswerDue ? note('answerBy', { date: date(p.submission.journalAnswerDue) }) : note(state === 'current' ? 'offered' : 'journalAccepted');
        case 'result':
            if (state === 'failed') return note('notAccepted');
            if (state === 'done') return note('accepted');
            return p.dates.notify ? note('scheduled', { date: date(p.dates.notify) }) : null;
        case 'final': return state === 'done' ? date(pub.value.cameraReady?.at) : (p.dates.cameraReady ? note('within', { date: date(p.dates.cameraReady) }) : null);
        case 'present': return date(pub.value.presentation?.at) ?? date(p.dates.conference);
        default: return null;
    }
}

const steps = computed(() => journeySteps({ status: s.value, editable: stillEditable.value, hadRevision: props.hadRevision, finalApproved: !!props.submission.cameraReadyApproved, finalFix: !!props.submission.cameraReadyFix })
    .map((step) => ({ ...step, note: noteFor(step) })));

// Days to a due date (the whole due day counts); negative = late
const daysTo = (due) => (due ? toDay(due.slice(0, 10)) - toDay(props.today) : null);

// Whose turn it is: { tone: mine|wait|done|end, icon, title, text, due?, action? }
const card = computed(() => {
    if (stillEditable.value) {
        return { tone: 'mine', icon: 'pen', title: 'editUntil', due: props.window.deadline, action: props.links.edit && { href: props.links.edit, label: 'edit' } };
    }
    if (isRevising.value) {
        return { tone: 'mine', icon: 'pen', title: 'revise', due: props.submission.revisionDueDate, action: props.links.edit && { href: props.links.edit, label: 'revise' } };
    }
    // Offered to a journal: the author accepts or declines on this card
    if (s.value === 'journal_selected') {
        return { tone: 'mine', icon: 'newspaper', title: 'journalOffer', params: { journal: journal.value?.name ?? '' }, due: props.submission.journalAnswerDue, answer: true };
    }
    if (s.value === 'accepted') {
        return { tone: 'mine', icon: 'file-circle-check', title: props.submission.cameraReadyFix ? 'cameraReadyFix' : 'cameraReady', due: props.dates.cameraReady, action: { href: '#publication', label: 'cameraReady', anchor: true } };
    }
    // Camera-ready sent: the editors check it before it goes into the proceedings
    if (s.value === 'camera_ready' && !props.submission.cameraReadyApproved) return { tone: 'wait', icon: 'magnifying-glass', title: 'cameraReadyCheck' };
    if (isRejected.value) return { tone: 'end', icon: 'circle-info', title: 'rejected', params: { date: date(props.dates.conference) } };
    if (presentingStatus.value) {
        // Accepted a journal offer: they continue on the journal's site; we call them back for the slides
        if (!pub.value.presentation && s.value === 'journal_accepted') {
            return { tone: 'done', icon: 'newspaper', title: 'atJournal', params: { journal: journal.value?.name ?? '' }, action: journal.value?.url && { href: journal.value.url, label: 'journal', external: true } };
        }
        if (!pub.value.presentation) return { tone: 'wait', icon: 'hourglass-half', title: 'scheduling' };
        const slot = { date: formatDate(pub.value.presentation.at, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }), room: pub.value.presentation.room ?? '—' };
        if (!pub.value.slides && pub.value.canUploadSlides) {
            return { tone: 'mine', icon: 'file-powerpoint', title: 'slides', due: pub.value.slidesUntil, params: slot, action: { href: '#publication', label: 'slides', anchor: true } };
        }
        return { tone: 'done', icon: 'circle-check', title: 'ready', params: slot };
    }
    return { tone: 'wait', icon: 'hourglass-half', title: s.value === 'revised' || props.hadRevision ? 'revised' : 'reviewing', params: { date: date(props.dates.notify) }, scheduled: !!props.dates.notify };
});
const cardDays = computed(() => daysTo(card.value.due));

const journal = computed(() => props.submission.journal);
// Answering the offer: the button pressed, confirmed in a modal
const answering = ref(null); // accept | decline
const shownAnswer = ref('accept');
const sending = ref(false);
function ask(choice) {
    shownAnswer.value = choice;
    answering.value = choice;
}
function answer() {
    if (!props.links.answer) {
        answering.value = null;
        return;
    }
    router.post(props.links.answer, { answer: answering.value }, {
        preserveScroll: true,
        onStart: () => (sending.value = true),
        onFinish: () => {
            sending.value = false;
            answering.value = null;
        },
    });
}

const dot = {
    done: 'border-emerald-500 bg-emerald-500 text-white',
    current: 'border-gold-400 bg-gold-400 text-black shadow-[0_0_0_4px_rgba(255,204,41,.25)]',
    upcoming: 'border-neutral-300 bg-white text-neutral-400 dark:border-white/15 dark:bg-ink-900 dark:text-neutral-500',
    failed: 'border-red-400 bg-red-50 text-red-500 dark:bg-red-500/10',
};
const tones = {
    mine: 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-100',
    wait: 'border-neutral-200 bg-neutral-50 text-neutral-700 dark:border-white/10 dark:bg-white/5 dark:text-neutral-200',
    done: 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-100',
    end: 'border-neutral-200 bg-neutral-50 text-neutral-700 dark:border-white/10 dark:bg-white/5 dark:text-neutral-200',
};
// Row layout: small dots, and the step the paper is on
const miniDot = {
    done: 'size-2.5 bg-emerald-500',
    current: 'size-3.5 bg-gold-400 ring-3 ring-gold-400/30',
    upcoming: 'size-2.5 bg-neutral-300 dark:bg-white/20',
    failed: 'size-3.5 bg-red-500 ring-3 ring-red-500/25',
};
const nowStep = computed(() => steps.value.find((x) => ['current', 'failed'].includes(x.state)) ?? steps.value[steps.value.length - 1]);
</script>

<template>
    <!-- One line, for comparing many papers at a glance -->
    <div v-if="layout === 'row'" class="grid items-center gap-x-6 gap-y-2 md:grid-cols-[13rem_1fr]">
        <div class="space-y-1.5">
            <span class="flex items-center" aria-hidden="true">
                <template v-for="(step, i) in steps" :key="step.key">
                    <span v-if="i" :class="steps[i - 1].state === 'done' ? 'bg-emerald-500' : 'bg-neutral-200 dark:bg-white/10'" class="h-0.5 w-4" />
                    <span :class="miniDot[step.state]" class="shrink-0 rounded-full" :title="t(`submissions.journey.step.${step.key}`)" />
                </template>
            </span>
            <p :class="nowStep.state === 'failed' ? 'text-red-600 dark:text-red-400' : ''" class="text-sm font-medium">
                {{ t(`submissions.journey.step.${nowStep.key}`) }}
                <span v-if="nowStep.note" class="font-normal text-neutral-500 dark:text-neutral-400">· {{ nowStep.note }}</span>
            </p>
        </div>
        <div class="flex min-w-0 items-start gap-3">
            <span :class="tones[card.tone]" class="shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap">
                <Fa :icon="card.icon" class="mr-1" />{{ t(`submissions.journey.turn.${card.tone}`) }}
            </span>
            <div class="min-w-0">
                <p class="text-sm font-medium">{{ t(`submissions.journey.card.${card.title}.title`, card.params ?? {}) }}</p>
                <p v-if="card.due" :class="cardDays < 0 ? 'text-red-600 dark:text-red-400' : 'text-neutral-500 dark:text-neutral-400'" class="text-xs">
                    <Fa icon="calendar-days" class="mr-1" />{{ cardDays < 0 ? t('submissions.journey.late', { date: date(card.due), days: -cardDays }) : cardDays === 0 ? t('submissions.journey.dueToday', { date: date(card.due) }) : t('submissions.journey.due', { date: date(card.due), days: cardDays }) }}
                </p>
                <p v-if="card.action || card.answer" class="text-xs text-sky-700 dark:text-sky-300">
                    <Fa icon="arrow-right" class="mr-1" />{{ card.answer ? `${t('submissions.journalOffer.accept')} / ${t('submissions.journalOffer.decline')}` : t(`submissions.journey.action.${card.action.label}`) }}
                </p>
            </div>
        </div>
    </div>

    <div v-else class="@container space-y-4">
        <!-- Steps: down on a narrow box, across on a wide one -->
        <ol class="flex flex-col @2xl:flex-row">
            <li
                v-for="(step, i) in steps"
                :key="step.key"
                class="relative flex gap-3 pb-5 last:pb-0 @2xl:flex-1 @2xl:flex-col @2xl:items-center @2xl:pb-0 @2xl:text-center"
                :aria-current="step.state === 'current' ? 'step' : undefined"
            >
                <span
                    v-if="i < steps.length - 1"
                    :class="step.state === 'done' ? 'bg-emerald-500' : 'bg-neutral-200 dark:bg-white/10'"
                    class="absolute top-10 bottom-1 left-4.5 w-0.5 -translate-x-1/2 @2xl:top-4.5 @2xl:right-[calc(-50%+1.5rem)] @2xl:bottom-auto @2xl:left-[calc(50%+1.5rem)] @2xl:h-0.5 @2xl:w-auto @2xl:translate-x-0"
                />
                <span :class="dot[step.state]" class="relative z-10 grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm transition">
                    <Fa :icon="step.state === 'done' ? 'check' : step.state === 'failed' ? 'xmark' : step.icon" />
                </span>
                <span class="min-w-0 pt-1 @2xl:px-2 @2xl:pt-0">
                    <span
                        :class="{
                            'font-semibold text-neutral-900 dark:text-white': step.state === 'current',
                            'text-neutral-700 dark:text-neutral-200': step.state === 'done',
                            'text-neutral-400 dark:text-neutral-500': step.state === 'upcoming',
                            'font-semibold text-red-600 dark:text-red-400': step.state === 'failed',
                        }"
                        class="block text-sm"
                    >{{ t(`submissions.journey.step.${step.key}`) }}</span>
                    <!-- A revision at this step: the author's turn (orange), or sent back and waiting to be checked (blue) -->
                    <span
                        v-if="step.badge"
                        :class="step.badge === 'revising' ? 'border-orange-300 bg-orange-50 text-orange-700 dark:border-orange-500/40 dark:bg-orange-500/15 dark:text-orange-300' : 'border-sky-300 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-300'"
                        class="mt-1 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                    ><Fa :icon="step.badge === 'revising' ? 'pen' : step.badge === 'checking' ? 'magnifying-glass' : 'rotate'" class="text-[0.65rem]" />{{ t(`submissions.journey.badge.${step.badge}`) }}</span>
                    <span v-if="step.note" class="mt-0.5 block text-xs text-neutral-500 dark:text-neutral-400">{{ step.note }}</span>
                </span>
            </li>
        </ol>

        <!-- Whose turn it is -->
        <div :class="tones[card.tone]" class="flex flex-col gap-3 rounded-xl border p-4 @xl:flex-row @xl:items-center">
            <Fa :icon="card.icon" class="hidden text-xl @xl:block" />
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold tracking-wide uppercase opacity-70">{{ t(`submissions.journey.turn.${card.tone}`) }}</p>
                <p class="mt-0.5 font-semibold">{{ t(`submissions.journey.card.${card.title}.title`, card.params ?? {}) }}</p>
                <p class="mt-1 text-sm opacity-90">
                    {{ t(`submissions.journey.card.${card.title}.text`, card.params ?? {}) }}
                    <template v-if="card.title === 'reviewing' || card.title === 'revised'">{{ card.scheduled ? t('submissions.journey.card.notifyOn', card.params) : t('submissions.journey.card.notifyByEmail') }}</template>
                </p>
                <p v-if="card.due" :class="cardDays < 0 ? 'text-red-600 dark:text-red-400' : cardDays <= 3 ? 'text-orange-600 dark:text-orange-300' : 'opacity-80'" class="mt-1.5 text-sm font-medium">
                    <Fa icon="calendar-days" class="mr-1" />
                    {{ cardDays < 0 ? t('submissions.journey.late', { date: date(card.due), days: -cardDays }) : cardDays === 0 ? t('submissions.journey.dueToday', { date: date(card.due) }) : t('submissions.journey.due', { date: date(card.due), days: cardDays }) }}
                </p>
                <!-- The journal offered: its terms and site, so the author can decide -->
                <div v-if="card.answer && journal" class="mt-2 space-y-1 rounded-lg bg-white/70 px-3 py-2 text-sm text-neutral-700 dark:bg-black/20 dark:text-neutral-200">
                    <p v-if="journal.terms" class="whitespace-pre-line"><span class="font-semibold">{{ t('submissions.journalOffer.terms') }}:</span> {{ journal.terms }}</p>
                    <a v-if="journal.url" :href="journal.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sky-700 underline-offset-2 hover:underline dark:text-sky-300">
                        {{ t('submissions.journalOffer.visit') }} <Fa icon="arrow-up-right-from-square" class="text-xs" />
                    </a>
                    <p class="text-xs opacity-80">{{ t('submissions.journalOffer.explain') }}</p>
                </div>
                <a v-if="messageFile && ['revise', 'cameraReady', 'cameraReadyFix', 'rejected'].includes(card.title)" :href="messageFile.url" class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-current/30 bg-white/70 px-2.5 py-1 text-xs font-medium transition hover:bg-white dark:bg-black/20">
                    <Fa icon="paperclip" /> {{ t('decision.attachedFile') }}: {{ messageFile.name }}
                </a>
                <blockquote v-if="message && ['revise', 'cameraReady', 'cameraReadyFix', 'rejected'].includes(card.title)" class="mt-2 rounded-lg bg-white/70 px-3 py-2 text-sm whitespace-pre-line text-neutral-700 dark:bg-black/20 dark:text-neutral-200">
                    <span class="mb-0.5 block text-xs font-semibold opacity-70">{{ t('submissions.journey.fromEditor') }}</span>{{ message }}
                </blockquote>
            </div>
            <div v-if="card.answer" class="flex shrink-0 flex-col gap-2 sm:flex-row @xl:flex-col">
                <button type="button" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="ask('accept')">
                    <Fa icon="circle-check" /> {{ t('submissions.journalOffer.accept') }}
                </button>
                <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium transition hover:border-gold-400 dark:border-white/20" @click="ask('decline')">
                    <Fa icon="arrow-right" /> {{ t('submissions.journalOffer.decline') }}
                </button>
            </div>
            <template v-if="card.action">
                <a v-if="card.action.external" :href="card.action.href" target="_blank" rel="noopener" class="flex shrink-0 items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                    {{ t(`submissions.journey.action.${card.action.label}`) }} <Fa icon="arrow-up-right-from-square" class="text-xs" />
                </a>
                <a v-else-if="card.action.anchor" :href="card.action.href" class="flex shrink-0 items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                    {{ t(`submissions.journey.action.${card.action.label}`) }} <Fa icon="arrow-right" class="text-xs" />
                </a>
                <Link v-else :href="card.action.href" class="flex shrink-0 items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="pen" class="text-xs" /> {{ t(`submissions.journey.action.${card.action.label}`) }}
                </Link>
            </template>
        </div>

        <ConfirmModal
            :show="!!answering"
            :icon="shownAnswer === 'accept' ? 'newspaper' : 'arrow-right'"
            :title="t(`submissions.journalOffer.confirm.${shownAnswer}.title`)"
            :message="t(`submissions.journalOffer.confirm.${shownAnswer}.text`, { journal: journal?.name ?? '' })"
            :confirm-label="t(`submissions.journalOffer.${shownAnswer}`)"
            :processing="sending"
            @close="answering = null"
            @confirm="answer"
        />
    </div>
</template>
