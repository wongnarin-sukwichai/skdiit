<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/Front/PageHeader.vue';
import { useI18n } from '@/i18n';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('nav.about')" />
    <PageHeader icon="building-columns" :title="t('about.title')" :lead="t('about.lead')" />

    <section class="mx-auto grid max-w-[1600px] gap-6 px-4 pb-12 sm:px-6 lg:px-8 lg:grid-cols-2">
        <div class="rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6">
            <h2 class="mb-4 text-xl font-semibold text-gold-400">{{ t('about.objectivesTitle') }}</h2>
            <ul class="space-y-3">
                <li v-for="(item, i) in t('about.objectives')" :key="i" class="flex gap-3 text-neutral-200">
                    <Fa icon="circle-check" class="mt-1 shrink-0 text-gold-400" />
                    {{ item }}
                </li>
            </ul>
        </div>
        <div class="rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6">
            <h2 class="mb-4 text-xl font-semibold text-gold-400">{{ t('about.topicsTitle') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="(topic, i) in t('about.topics')" :key="i" class="rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-neutral-200">
                    {{ topic }}
                </div>
            </div>
        </div>
    </section>
</template>
