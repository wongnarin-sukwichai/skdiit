<script setup>
import { useI18n } from '@/i18n';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import ReviewBadge from '@/Components/Admin/ReviewBadge.vue';

defineProps({
    history: { type: Array, required: true }, // SubmissionEvent::toClient(), newest first
});

const { t, tr, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const icons = {
    submitted: 'paper-plane',
    updated: 'pen',
    resubmitted: 'arrow-rotate-right',
    screened: 'clipboard-check',
    track_changed: 'layer-group',
    reviewer_assigned: 'user-plus',
    reviewer_removed: 'user-xmark',
    review_due_changed: 'calendar-days',
    review_submitted: 'clipboard-check',
    decided: 'gavel',
    review_round_started: 'user-check',
    option_chosen: 'book',
    camera_ready_uploaded: 'file-circle-check',
    journal_result: 'newspaper',
    presentation_scheduled: 'person-chalkboard',
};
const day = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric' });

// Screening and decision entries are labelled by their outcome
const title = (event) => (['screened', 'decided'].includes(event.action)
    ? t(`submissions.history.${event.action}.${event.toStatus}`)
    : t(`submissions.history.${event.action}`, event.meta ?? {}));
</script>

<template>
    <section class="rounded-xl border border-neutral-200 p-4 dark:border-white/10">
        <h2 class="mb-4 flex items-center gap-2 font-semibold"><Fa icon="clock-rotate-left" class="text-gold-400" /> {{ t('submissions.history.title') }}</h2>

        <p v-if="!history.length" class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('submissions.history.empty') }}</p>

        <ol v-else class="relative space-y-5 border-l border-neutral-200 pl-6 dark:border-white/10">
            <li v-for="event in history" :key="event.id" class="relative">
                <span class="absolute top-0 -left-9.25 grid size-7 place-items-center rounded-full border border-neutral-200 bg-white text-xs text-gold-500 dark:border-white/10 dark:bg-ink-900">
                    <Fa :icon="icons[event.action]" />
                </span>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="font-medium">{{ title(event) }}</span>
                    <SubmissionStatusBadge v-if="event.toStatus" :status="event.toStatus" />
                </div>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ when(event.at) }}
                    <template v-if="event.actor"> · {{ event.actor.name }} ({{ t(`admin.role.${event.actor.role === 'user' ? 'author' : event.actor.role}`) }})</template>
                </p>
                <p v-if="event.action === 'track_changed'" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                    {{ tr(event.meta.from) }} <Fa icon="arrow-right" class="mx-1 text-xs" /> {{ tr(event.meta.to) }}
                </p>
                <p v-else-if="event.action === 'reviewer_assigned'" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                    {{ event.meta.reviewer }} · {{ t('reviews.due') }} {{ day(event.meta.due) }}
                </p>
                <p v-else-if="event.action === 'reviewer_removed'" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{{ event.meta.reviewer }}</p>
                <p v-else-if="event.action === 'review_due_changed'" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                    {{ event.meta.reviewer }}: {{ day(event.meta.from) }} <Fa icon="arrow-right" class="mx-1 text-xs" /> {{ day(event.meta.to) }}
                </p>
                <p v-else-if="event.action === 'decided' && event.meta?.due" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                    <Fa icon="calendar-days" class="mr-1" /> {{ t('submissions.outcome.due', { date: day(event.meta.due) }) }}
                </p>
                <p v-else-if="event.action === 'option_chosen'" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{{ t(`publication.option.${event.meta.option}.title`) }}</p>
                <p v-else-if="event.action === 'journal_result'" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                    {{ event.meta.journal }} · {{ t(`publication.journal.${event.meta.result}Option`) }}
                </p>
                <p v-else-if="event.action === 'presentation_scheduled'" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                    <template v-if="event.meta.at">{{ when(event.meta.at) }} · {{ t('publication.presentation.room') }} {{ event.meta.room }}</template>
                    <template v-else>{{ t('publication.presentation.cleared') }}</template>
                </p>
                <p v-else-if="event.action === 'review_submitted'" class="mt-1">
                    <ReviewBadge kind="recommendation" :value="event.meta.recommendation" />
                </p>
                <blockquote v-if="event.comment" class="mt-2 rounded-lg bg-neutral-50 px-3 py-2 text-sm whitespace-pre-line text-neutral-700 dark:bg-white/5 dark:text-neutral-200">
                    {{ event.comment }}
                </blockquote>
            </li>
        </ol>
    </section>
</template>
