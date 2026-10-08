<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import UsersTab from '@/Components/Admin/Settings/UsersTab.vue';
import TracksTab from '@/Components/Admin/Settings/TracksTab.vue';
import ReviewerTracksTab from '@/Components/Admin/Settings/ReviewerTracksTab.vue';
import SubmissionTab from '@/Components/Admin/Settings/SubmissionTab.vue';
import { useI18n } from '@/i18n';

defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    staffRoles: { type: Array, required: true },
    tracks: { type: Array, required: true },
    reviewers: { type: Array, required: true },
    submission: { type: Object, required: true },
});

const { t } = useI18n();

const tabs = [
    { key: 'users', icon: 'users' },
    { key: 'tracks', icon: 'layer-group' },
    { key: 'reviewers', icon: 'user-check' },
    { key: 'submission', icon: 'file-arrow-up' },
];
const tab = ref('users');
</script>

<template>
    <Head :title="t('admin.menu.settings')" />

    <div class="space-y-6">
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
                <UsersTab v-if="tab === 'users'" key="users" :users="users" :filters="filters" :staff-roles="staffRoles" />
                <TracksTab v-else-if="tab === 'tracks'" key="tracks" :tracks="tracks" />
                <ReviewerTracksTab v-else-if="tab === 'reviewers'" key="reviewers" :reviewers="reviewers" :tracks="tracks" @go-users="tab = 'users'" />
                <SubmissionTab v-else key="submission" :settings="submission" />
            </Transition>
        </Card>
    </div>
</template>
