<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/Front/PageHeader.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

// Texts come from Settings › Website content; the topics are the tracks (admin renames them in Tracks)
defineProps({
    topics: { type: Array, required: true }, // tracks: { id, name: { en, th } }
});

const { t, tr } = useI18n();
const site = useSite();
</script>

<template>
    <Head :title="t('nav.about')" />
    <PageHeader icon="building-columns" :title="t('about.title')" :lead="tr(site.about.lead)" />

    <section class="mx-auto grid max-w-[1600px] gap-6 px-4 pb-12 sm:px-6 lg:px-8 lg:grid-cols-2">
        <div v-if="site.about.objectives.length" class="rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6">
            <h2 class="mb-4 text-xl font-semibold text-gold-400">{{ t('about.objectivesTitle') }}</h2>
            <ul class="space-y-3">
                <li v-for="(item, i) in site.about.objectives" :key="i" class="flex gap-3 text-neutral-200">
                    <Fa icon="circle-check" class="mt-1 shrink-0 text-gold-400" />
                    {{ tr(item) }}
                </li>
            </ul>
        </div>
        <div class="rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6">
            <h2 class="mb-4 text-xl font-semibold text-gold-400">{{ t('about.topicsTitle') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="topic in topics" :key="topic.id" class="rounded-xl border border-white/10 bg-white/3 px-4 py-3 text-sm text-neutral-200">
                    {{ tr(topic.name) }}
                </div>
            </div>
        </div>
    </section>
</template>
