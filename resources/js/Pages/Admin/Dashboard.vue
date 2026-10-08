<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';
import { affiliationByKey, registrants, statuses } from '@/data/mock';

const { t, tr, formatDate, formatNumber } = useI18n();
const { accessTo } = useModules();
const full = computed(() => accessTo('dashboard') === 'full');
const user = computed(() => usePage().props.auth.user);

const latest = registrants.slice(0, 6);

const statusCounts = statuses.map((status) => ({
    status,
    count: registrants.filter((r) => r.status === status).length,
}));

const topAffiliations = computed(() => {
    const counts = {};
    registrants.forEach((r) => (counts[r.affiliation] = (counts[r.affiliation] ?? 0) + 1));
    return Object.entries(counts)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 6)
        .map(([key, count]) => ({ key, count }));
});
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />

    <!-- Authors, reviewers and finance get their own dashboard (still to be designed) -->
    <Card v-if="!full" class="space-y-10">
        <PageHeader icon="gauge" :title="t('admin.dashboard.title')" :subtitle="t('admin.dashboard.welcome', { name: user.name })" />
        <div class="flex flex-col items-center py-16 text-center">
            <span class="mb-5 grid size-20 place-items-center rounded-full bg-gold-100 text-3xl text-gold-500 dark:bg-gold-400/15 dark:text-gold-400">
                <Fa icon="screwdriver-wrench" />
            </span>
            <h2 class="text-xl font-semibold">{{ t('common.comingSoon') }}</h2>
            <p class="mt-2 max-w-md text-neutral-500 dark:text-neutral-400">{{ t('admin.placeholder') }}</p>
        </div>
    </Card>

    <div v-else class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="gauge" :title="t('admin.dashboard.title')" :subtitle="t('admin.dashboard.subtitle')" />
            <StatCards />
        </Card>

        <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
            <Card>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        <Fa icon="clock" class="text-gold-400" /> {{ t('admin.dashboard.recent') }}
                    </h2>
                    <Link href="/admin/registrants" class="text-sm text-gold-600 hover:underline dark:text-gold-400">
                        {{ t('admin.dashboard.viewAll') }} <Fa icon="chevron-right" class="text-xs" />
                    </Link>
                </div>
                <ul class="divide-y divide-neutral-200 dark:divide-white/10">
                    <li v-for="row in latest" :key="row.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 text-sm">
                        <span class="grid size-9 place-items-center rounded-full bg-gold-100 text-gold-600 dark:bg-gold-400/15 dark:text-gold-400">
                            <Fa icon="user" class="text-xs" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ tr(row.name) }}</span>
                            <span class="block truncate text-neutral-500 dark:text-neutral-400">{{ tr(affiliationByKey[row.affiliation]) }}</span>
                        </span>
                        <StatusBadge :status="row.status" />
                        <span class="w-24 text-right text-neutral-500 dark:text-neutral-400">{{ formatDate(row.registeredAt) }}</span>
                    </li>
                </ul>
            </Card>

            <div class="space-y-6">
                <Card>
                    <h2 class="mb-4 text-lg font-semibold">{{ t('admin.dashboard.byStatus') }}</h2>
                    <ul class="space-y-3">
                        <li v-for="item in statusCounts" :key="item.status" class="flex items-center justify-between text-sm">
                            <StatusBadge :status="item.status" />
                            <span class="font-semibold">{{ formatNumber(item.count) }}</span>
                        </li>
                    </ul>
                </Card>
                <Card>
                    <h2 class="mb-4 text-lg font-semibold">{{ t('admin.dashboard.byAffiliation') }}</h2>
                    <ul class="space-y-3">
                        <li v-for="item in topAffiliations" :key="item.key" class="flex items-center justify-between gap-4 text-sm">
                            <span class="truncate text-neutral-600 dark:text-neutral-300">{{ tr(affiliationByKey[item.key]) }}</span>
                            <span class="font-semibold">{{ formatNumber(item.count) }}</span>
                        </li>
                    </ul>
                </Card>
            </div>
        </div>
    </div>
</template>
