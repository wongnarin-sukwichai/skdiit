<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/Front/PageHeader.vue';
import { useI18n } from '@/i18n';
import { importantDates, program } from '@/data/mock';

const { t, tr, formatDate } = useI18n();
</script>

<template>
    <Head :title="t('nav.schedule')" />
    <PageHeader icon="calendar-days" :title="t('schedule.title')" :lead="t('schedule.lead')" />

    <section class="mx-auto grid max-w-[1600px] gap-6 px-4 pb-12 sm:px-6 lg:px-8 lg:grid-cols-[1.2fr_1fr]">
        <ol class="relative space-y-4 border-l-2 border-gold-400/30 pl-6">
            <li v-for="item in importantDates" :key="item.date" class="relative rounded-xl border border-white/10 bg-ink-900/80 p-4">
                <span class="absolute top-5 -left-[33px] size-4 rounded-full border-4 border-ink-950 bg-gold-400 shadow-[0_0_10px_rgba(255,204,41,.8)]" />
                <p class="text-sm font-semibold text-gold-400">{{ formatDate(item.date, { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
                <p class="text-neutral-100">{{ tr(item.label) }}</p>
            </li>
        </ol>

        <div class="h-fit rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6">
            <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-gold-400">
                <Fa icon="clock" /> {{ t('schedule.program') }}
            </h2>
            <ul class="divide-y divide-white/10">
                <li v-for="item in program" :key="item.time" class="flex gap-4 py-3 text-sm">
                    <span class="w-28 shrink-0 font-medium text-gold-300">{{ item.time }}</span>
                    <span class="text-neutral-200">{{ tr(item.label) }}</span>
                </li>
            </ul>
        </div>
    </section>
</template>
