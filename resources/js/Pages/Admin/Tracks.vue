<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import TrackBoard from '@/Components/Admin/Tracks/TrackBoard.vue';
import TrackNames from '@/Components/Admin/Tracks/TrackNames.vue';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';

// Tracks (กลุ่มสาขา), admin only
defineProps({
    tracks: { type: Array, required: true },
    reviewers: { type: Array, required: true },
});

const { t } = useI18n();
// Editors see the board and names but cannot change them
const readonly = useModules().accessTo('tracks') !== 'full';

const tabs = [
    { key: 'board', icon: 'users' },
    { key: 'names', icon: 'pen' },
];
const tab = ref('board');
</script>

<template>
    <Head :title="t('admin.menu.tracks')" />

    <Card class="space-y-6">
        <PageHeader icon="layer-group" :title="t('admin.menu.tracks')" :subtitle="t('admin.tracks.subtitle')" />

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
                <Fa :icon="item.icon" /> {{ t(`admin.tracks.tabs.${item.key}`) }}
            </button>
        </nav>

        <Transition name="fade" mode="out-in">
            <TrackBoard v-if="tab === 'board'" key="board" :tracks="tracks" :reviewers="reviewers" :readonly="readonly" />
            <TrackNames v-else key="names" :tracks="tracks" :readonly="readonly" />
        </Transition>
    </Card>
</template>
