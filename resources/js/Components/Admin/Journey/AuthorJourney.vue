<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { REJECTED, REVISING, journeySteps } from '@/utils/journey';
import { toDay, today as todayIn } from '@/utils/timeline';

/*
 * A paper's path as its author sees it: up to six steps in the author's own words (the journal, screening, review and
 * decisions are one "under consideration" step; the journal is never named until it selects the paper), and one card
 * that says whose turn it is: the author's task with its due date and a button, or what the organisers are doing.
 * Steps run across on a wide box and down on a narrow one (container query, so the preview can show both).
 */
const props = defineProps({
    // { status, submittedAt, revisionDueDate, publication?: { cameraReady, presentation: { at, room }, slides, slidesUntil, canUploadSlides } }
    submission: { type: Object, required: true },
    window: { type: Object, required: true }, // { deadline, phase: upcoming|open|closed } (Submission::window())
    dates: { type: Object, default: () => ({}) }, // public schedule: { notify, cameraReady, conference } (Y-m-d)
    hadRevision: { type: Boolean, default: false }, // the author sent a revision before (shows the revision step as done)
    message: { type: String, default: null }, // the editor's latest decision message, shown with a revision, acceptance or rejection
    links: { type: Object, default: () => ({}) }, // { edit, journal } (journal = the journal's submission site)
    today: { type: String, default: () => todayIn() },
});

const { t, formatDate } = useI18n();

const s = computed(() => props.submission.status);
const pub = computed(() => props.submission.publication ?? {});
const presentingStatus = computed(() => ['camera_ready', 'journal_accepted'].includes(s.value));
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
        case 'review': return state === 'upcoming' && p.window.deadline ? note('afterClose', { date: date(p.window.deadline) }) : (state === 'current' ? note('inProgress') : null);
        case 'journal': return note(state === 'current' ? 'onJournalSite' : 'journalAccepted');
        case 'revision': return state === 'current' && p.submission.revisionDueDate ? note('within', { date: date(p.submission.revisionDueDate) }) : null;
        case 'result':
            if (state === 'failed') return note('notAccepted');
            if (state === 'done') return note('accepted');
            return p.dates.notify ? note('scheduled', { date: date(p.dates.notify) }) : null;
        case 'final': return state === 'done' ? date(pub.value.cameraReady?.at) : (p.dates.cameraReady ? note('within', { date: date(p.dates.cameraReady) }) : null);
        case 'present': return date(pub.value.presentation?.at) ?? date(p.dates.conference);
        default: return null;
    }
}

const steps = computed(() => journeySteps({ status: s.value, editable: stillEditable.value, hadRevision: props.hadRevision })
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
    if (s.value === 'journal_selected') {
        return { tone: 'mine', icon: 'newspaper', title: 'journal', action: props.links.journal && { href: props.links.journal, label: 'journal', external: true } };
    }
    if (s.value === 'accepted') {
        return { tone: 'mine', icon: 'file-circle-check', title: 'cameraReady', due: props.dates.cameraReady, action: { href: '#publication', label: 'cameraReady', anchor: true } };
    }
    if (isRejected.value) return { tone: 'end', icon: 'circle-info', title: 'rejected', params: { date: date(props.dates.conference) } };
    if (presentingStatus.value) {
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
</script>

<template>
    <div class="@container space-y-4">
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
                <blockquote v-if="message && ['revise', 'cameraReady', 'rejected'].includes(card.title)" class="mt-2 rounded-lg bg-white/70 px-3 py-2 text-sm whitespace-pre-line text-neutral-700 dark:bg-black/20 dark:text-neutral-200">
                    <span class="mb-0.5 block text-xs font-semibold opacity-70">{{ t('submissions.journey.fromEditor') }}</span>{{ message }}
                </blockquote>
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
    </div>
</template>
