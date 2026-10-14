<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import SubmissionTab from '@/Components/Admin/Settings/SubmissionTab.vue';
import AcademicTitlesTab from '@/Components/Admin/Settings/AcademicTitlesTab.vue';
import AffiliationsTab from '@/Components/Admin/Settings/AffiliationsTab.vue';
import FeesTab from '@/Components/Admin/Settings/FeesTab.vue';
import { useI18n } from '@/i18n';

// System settings: submission rules, academic titles and affiliations (accounts are under Staff / Reviewers, track names under Tracks)
defineProps({
    submission: { type: Object, required: true },
    academicTitles: { type: Array, required: true },
    affiliationList: { type: Array, required: true }, // not "affiliations": that name is the shared sign-up list
    typedAffiliations: { type: Array, required: true },
});

const { t } = useI18n();

const tabs = [
    { key: 'submission', icon: 'file-arrow-up' },
    { key: 'academicTitles', icon: 'user-tie' },
    { key: 'affiliations', icon: 'building-columns' },
    { key: 'fees', icon: 'receipt' },
];
const tab = ref('submission');
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
            <SubmissionTab v-if="tab === 'submission'" key="submission" :settings="submission" />
            <AcademicTitlesTab v-else-if="tab === 'academicTitles'" key="academicTitles" :titles="academicTitles" />
            <FeesTab v-else-if="tab === 'fees'" key="fees" />
            <AffiliationsTab v-else key="affiliations" :affiliations="affiliationList" :typed="typedAffiliations" />
        </Transition>
    </Card>
</template>
