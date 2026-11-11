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
});

const { t } = useI18n();
const today = todayIn();

const planned = (key) => timelinePlan.find((p) => p.key === key);
const dates = useConferenceDates();
const deadline = computed(() => props.deadline ?? `${planned('submission').end}T23:59:00+07:00`);

// overview: one row per scenario (click to open it in full); desktop / mobile: every scenario in full at that width
const width = ref('overview');
const opened = ref(null); // the scenario opened in the overview
const at = (days, time = '10:00') => `${addDays(today, days)}T${time}:00+07:00`;
const slot = { at: `${dates.value.conference}T10:30:00+07:00`, room: 'ห้อง 2 (SC-201)' };

// One made-up paper per scenario
const base = { submittedAt: at(-20) };
// A partner journal for the journal scenarios
const journal = { name: 'Journal of Applied Informatics and Technology (JIT)', url: 'https://ph01.tci-thaijo.org/index.php/jait', terms: 'ไม่จำกัดจำนวนบทความ ฟรี' };
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
    { key: 'journalSelected', submission: { ...base, status: 'journal_selected', journal, journalAnswerDue: addDays(today, 5) } },
    { key: 'journalAtJournal', submission: { ...base, status: 'journal_accepted', journal } },
    { key: 'journalAccepted', submission: { ...base, status: 'journal_accepted', journal, publication: { presentation: slot, canUploadSlides: true, slidesUntil: addDays(today, 6) } } },
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
                    v-for="mode in ['overview', 'desktop', 'mobile']"
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

        <!-- Overview: every scenario on one row, to compare them at a glance -->
        <div v-if="width === 'overview'" class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-240 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="w-10 px-4 py-3 font-medium">#</th>
                        <th class="px-3 py-3 font-medium">{{ t('admin.preview.journey.columns.scenario') }}</th>
                        <th class="px-3 py-3 font-medium">{{ t('admin.preview.journey.columns.journey') }}</th>
                        <th class="w-10 px-3 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                    <template v-for="(scenario, i) in scenarios" :key="scenario.key">
                        <tr
                            :class="opened === scenario.key ? 'bg-gold-50 dark:bg-gold-400/5' : 'hover:bg-neutral-50 dark:hover:bg-white/5'"
                            class="cursor-pointer align-top transition"
                            :aria-expanded="opened === scenario.key"
                            @click="opened = opened === scenario.key ? null : scenario.key"
                        >
                            <td class="px-4 py-3 text-xs font-semibold text-neutral-500">{{ i + 1 }}</td>
                            <td class="w-72 px-3 py-3">
                                <p class="font-medium">{{ t(`admin.preview.journey.scenario.${scenario.key}`) }}</p>
                                <SubmissionStatusBadge :status="scenario.submission.status" class="mt-1" />
                            </td>
                            <td class="px-3 py-3">
                                <AuthorJourney
                                    layout="row"
                                    :submission="scenario.submission"
                                    :window="{ deadline, phase: scenario.phase ?? 'closed' }"
                                    :dates="dates"
                                    :had-revision="!!scenario.hadRevision"
                                    :today="today"
                                />
                            </td>
                            <td class="px-3 py-3 text-neutral-400">
                                <Fa icon="chevron-down" :class="opened === scenario.key ? 'rotate-180' : ''" class="transition" />
                            </td>
                        </tr>
                        <tr v-if="opened === scenario.key">
                            <td colspan="4" class="bg-neutral-50/60 px-4 py-4 dark:bg-white/2">
                                <AuthorJourney
                                    :submission="scenario.submission"
                                    :window="{ deadline, phase: scenario.phase ?? 'closed' }"
                                    :dates="dates"
                                    :had-revision="!!scenario.hadRevision"
                                    :message="scenario.message"
                                    :links="{ edit: '#' }"
                                    :today="today"
                                />
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-else :class="width === 'mobile' ? 'grid justify-center gap-6 sm:grid-cols-[repeat(auto-fill,375px)]' : 'space-y-6'">
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
                    :links="{ edit: '#' }"
                    :today="today"
                />
            </section>
        </div>
    </Card>
</template>
