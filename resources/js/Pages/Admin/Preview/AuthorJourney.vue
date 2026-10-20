<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import AuthorJourney from '@/Components/Admin/Journey/AuthorJourney.vue';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import { timelinePlan } from '@/data/mock';
import { addDays, today as todayIn } from '@/utils/timeline';
import { useI18n } from '@/i18n';
import { useConferenceDates } from '@/composables/useConferenceDates';

// Design preview: the author's journey in every status with made-up papers, at desktop or phone width, before it
// replaces the notices on the author pages. Dates: the public rows of the timeline (assumed), the real deadline and conference day.
const props = defineProps({
    deadline: { type: String, default: null },
    journalUrl: { type: String, default: null },
});

const { t } = useI18n();
const today = todayIn();

const planned = (key) => timelinePlan.find((p) => p.key === key);
const dates = useConferenceDates();
const deadline = computed(() => props.deadline ?? `${planned('submission').end}T23:59:00+07:00`);

const width = ref('desktop');
const at = (days, time = '10:00') => `${addDays(today, days)}T${time}:00+07:00`;
const slot = { at: `${dates.value.conference}T10:30:00+07:00`, room: 'ห้อง 2 (SC-201)' };

// One made-up paper per scenario
const base = { submittedAt: at(-20) };
const scenarios = computed(() => [
    { key: 'open', submission: { ...base, status: 'submitted' }, phase: 'open' },
    { key: 'closed', submission: { ...base, status: 'submitted' } },
    { key: 'inReview', submission: { ...base, status: 'in_review' } },
    { key: 'revise', submission: { ...base, status: 'review_revision', revisionDueDate: addDays(today, 5) }, message: t('admin.preview.journey.sampleMessage') },
    { key: 'reviseLate', submission: { ...base, status: 'screening_revision', revisionDueDate: addDays(today, -2) } },
    { key: 'revised', submission: { ...base, status: 'revised' }, hadRevision: true },
    { key: 'reviewAgain', submission: { ...base, status: 'in_review' }, hadRevision: true },
    { key: 'accepted', submission: { ...base, status: 'accepted' }, hadRevision: true },
    { key: 'scheduling', submission: { ...base, status: 'camera_ready', publication: { cameraReady: { at: at(-3) } } } },
    { key: 'slides', submission: { ...base, status: 'camera_ready', publication: { cameraReady: { at: at(-3) }, presentation: slot, canUploadSlides: true, slidesUntil: addDays(today, 6) } } },
    { key: 'ready', submission: { ...base, status: 'camera_ready', publication: { cameraReady: { at: at(-3) }, presentation: slot, slides: { at: at(-1) } } } },
    { key: 'journalSelected', submission: { ...base, status: 'journal_selected' } },
    { key: 'journalAccepted', submission: { ...base, status: 'journal_accepted', publication: { presentation: slot, canUploadSlides: true, slidesUntil: addDays(today, 6) } } },
    { key: 'rejected', submission: { ...base, status: 'rejected' } },
]);
</script>

<template>
    <Head :title="t('admin.preview.journey.title')" />

    <Card class="space-y-6">
        <PageHeader icon="eye" :title="t('admin.preview.journey.title')" :subtitle="t('admin.preview.journey.subtitle')" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex max-w-3xl gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                <Fa icon="flask" class="mt-0.5 shrink-0" /> {{ t('admin.preview.journey.note') }}
            </p>
            <div class="flex rounded-lg border border-neutral-200 p-0.5 text-xs dark:border-white/10" role="group">
                <button
                    v-for="mode in ['desktop', 'mobile']"
                    :key="mode"
                    type="button"
                    :aria-pressed="width === mode"
                    :class="width === mode ? 'bg-gold-400 text-black' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    class="rounded-md px-3 py-1.5 font-medium transition"
                    @click="width = mode"
                >
                    {{ t(`admin.preview.journey.width.${mode}`) }}
                </button>
            </div>
        </div>

        <div :class="width === 'mobile' ? 'grid justify-center gap-6 sm:grid-cols-[repeat(auto-fill,375px)]' : 'space-y-6'">
            <section
                v-for="(scenario, i) in scenarios"
                :key="scenario.key"
                :class="width === 'mobile' ? 'w-full max-w-[375px]' : ''"
                class="rounded-xl border border-neutral-200 p-4 transition-all sm:p-5 dark:border-white/10"
            >
                <header class="mb-4 flex flex-wrap items-center gap-2 border-b border-dashed border-neutral-200 pb-3 dark:border-white/10">
                    <span class="grid size-6 place-items-center rounded-full bg-neutral-100 text-xs font-semibold dark:bg-white/10">{{ i + 1 }}</span>
                    <h3 class="font-semibold">{{ t(`admin.preview.journey.scenario.${scenario.key}`) }}</h3>
                    <SubmissionStatusBadge :status="scenario.submission.status" />
                </header>
                <AuthorJourney
                    :submission="scenario.submission"
                    :window="{ deadline, phase: scenario.phase ?? 'closed' }"
                    :dates="dates"
                    :had-revision="!!scenario.hadRevision"
                    :message="scenario.message"
                    :links="{ edit: '#', journal: journalUrl ?? 'https://example.org' }"
                    :today="today"
                />
            </section>
        </div>
    </Card>
</template>
