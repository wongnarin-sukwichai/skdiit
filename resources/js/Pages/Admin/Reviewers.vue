<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import AccountsTable from '@/Components/Admin/Accounts/AccountsTable.vue';
import ReviewerTracks from '@/Components/Admin/Reviewers/ReviewerTracks.vue';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';

defineProps({
    accounts: { type: Object, required: true },
    filters: { type: Object, required: true },
    tracks: { type: Array, required: true },
    reviewers: { type: Array, required: true }, // every reviewer, for the tracks tab
});

const { t } = useI18n();

// Admin manages reviewers; editors can only view
const { accessTo } = useModules();
const canEdit = computed(() => accessTo('reviewers') === 'full');

const tabs = [
    { key: 'accounts', icon: 'user-check' },
    { key: 'tracks', icon: 'layer-group' },
];
const tab = ref('accounts');
</script>

<template>
    <Head :title="t('admin.menu.reviewers')" />

    <Card class="space-y-6">
        <PageHeader icon="user-check" :title="t('admin.menu.reviewers')" :subtitle="t('admin.reviewers.subtitle')" />

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
                <Fa :icon="item.icon" /> {{ t(`admin.reviewers.tabs.${item.key}`) }}
            </button>
        </nav>

        <Transition name="fade" mode="out-in">
            <AccountsTable
                v-if="tab === 'accounts'"
                key="accounts"
                :accounts="accounts"
                :filters="filters"
                :roles="['reviewer']"
                :tracks="tracks"
                :can-edit="canEdit"
                base-url="/admin/reviewers"
                :add-label="t('admin.reviewers.add')"
            />
            <ReviewerTracks v-else key="tracks" :reviewers="reviewers" :tracks="tracks" :can-edit="canEdit" @go-accounts="tab = 'accounts'" />
        </Transition>
    </Card>
</template>
