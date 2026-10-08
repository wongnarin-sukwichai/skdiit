<script setup>
import { useI18n } from '@/i18n';
import { registrantStats } from '@/data/mock';

const { t, formatNumber } = useI18n();

const cards = [
    { key: 'total', icon: 'user' },
    { key: 'attend', icon: 'users' },
    { key: 'submitted', icon: 'file-lines' },
    { key: 'underReview', icon: 'file-circle-check' },
];
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
            v-for="card in cards"
            :key="card.key"
            class="relative flex items-center gap-4 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition-colors duration-300 dark:border-gold-400/25 dark:bg-linear-to-br dark:from-ink-800 dark:to-ink-900"
        >
            <div class="gold-streaks pointer-events-none absolute inset-0 hidden opacity-20 dark:block" />
            <span class="relative grid size-14 shrink-0 place-items-center rounded-full bg-gold-100 text-2xl text-gold-500 dark:bg-black/40 dark:text-gold-400">
                <Fa :icon="card.icon" />
            </span>
            <div class="relative">
                <p class="text-3xl font-bold">{{ formatNumber(registrantStats[card.key]) }}</p>
                <p class="text-sm text-neutral-500 dark:text-neutral-300">{{ t(`admin.stats.${card.key}`) }}</p>
            </div>
        </div>
    </div>
</template>
