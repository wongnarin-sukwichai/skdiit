<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import SubmissionTab from '@/Components/Admin/Settings/SubmissionTab.vue';
import AcademicTitlesTab from '@/Components/Admin/Settings/AcademicTitlesTab.vue';
import AffiliationsTab from '@/Components/Admin/Settings/AffiliationsTab.vue';
import ReviewCriteriaTab from '@/Components/Admin/Settings/ReviewCriteriaTab.vue';
import ConferenceTab from '@/Components/Admin/Settings/ConferenceTab.vue';
import WebsiteTab from '@/Components/Admin/Settings/WebsiteTab.vue';
import ScheduleTab from '@/Components/Admin/Settings/ScheduleTab.vue';
import YearTab from '@/Components/Admin/Settings/YearTab.vue';
import { useI18n } from '@/i18n';

// System settings: submission rules, academic titles and affiliations (accounts are under Staff / Reviewers, track names under Tracks)
defineProps({
    submission: { type: Object, required: true },
    academicTitles: { type: Array, required: true },
    affiliationList: { type: Array, required: true }, // not "affiliations": that name is the shared sign-up list
    typedAffiliations: { type: Array, required: true },
    reviewCriteria: { type: Array, required: true }, // scoring criteria with how many evaluations used each
    siteDocuments: { type: Array, required: true }, // files on the public Documents page
    yearSummary: { type: Object, required: true }, // the current conference year (ConferenceYearController::summary)
});

const { t } = useI18n();

const tabs = [
    { key: 'conference', icon: 'calendar-days' },
    { key: 'website', icon: 'globe' },
    { key: 'schedule', icon: 'clock' },
    { key: 'submission', icon: 'file-arrow-up' },
    { key: 'academicTitles', icon: 'user-tie' },
    { key: 'affiliations', icon: 'building-columns' },
    { key: 'reviewCriteria', icon: 'list-check' },
    { key: 'year', icon: 'database' },
];
const tab = ref('conference');
</script>

<template>
    <Head :title="t('admin.menu.settings')" />

    <Card class="space-y-6">
        <PageHeader icon="gear" :title="t('admin.menu.settings')" :subtitle="t('admin.settings.subtitle')" />

        <nav class="flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-white/10" role="tablist">
            <button
                v-for="item in tabs"
                :key="item.key"
                role="tab"
                :aria-selected="tab === item.key"
                :class="tab === item.key
                    ? 'border-gold-400 text-gold-600 dark:text-gold-400'
                    : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                @click="tab = item.key"
            >
                <Fa :icon="item.icon" /> {{ t(`admin.settings.tabs.${item.key}`) }}
            </button>
        </nav>

        <Transition name="fade" mode="out-in">
            <ConferenceTab v-if="tab === 'conference'" key="conference" />
            <ScheduleTab v-else-if="tab === 'schedule'" key="schedule" />
            <YearTab v-else-if="tab === 'year'" key="year" :summary="yearSummary" />
            <WebsiteTab v-else-if="tab === 'website'" key="website" :documents="siteDocuments" :max-mb="submission.maxMb" />
            <SubmissionTab v-else-if="tab === 'submission'" key="submission" :settings="submission" />
            <AcademicTitlesTab v-else-if="tab === 'academicTitles'" key="academicTitles" :titles="academicTitles" />
            <ReviewCriteriaTab v-else-if="tab === 'reviewCriteria'" key="reviewCriteria" :criteria="reviewCriteria" />
            <AffiliationsTab v-else key="affiliations" :affiliations="affiliationList" :typed="typedAffiliations" />
        </Transition>
    </Card>
</template>
