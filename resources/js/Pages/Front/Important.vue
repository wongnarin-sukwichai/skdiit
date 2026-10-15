<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/Front/PageHeader.vue';
import { useI18n } from '@/i18n';
import { formatFileSize } from '@/utils/format';

// Files admin uploads in Settings › Website content
defineProps({
    documents: { type: Array, required: true }, // { id, name: { en, th }, type, size, url }
});

const { t, tr } = useI18n();
</script>

<template>
    <Head :title="t('nav.important')" />
    <PageHeader icon="file-lines" :title="t('important.title')" :lead="t('important.lead')" />

    <section class="mx-auto grid max-w-[1600px] gap-4 px-4 pb-12 sm:px-6 lg:px-8 md:grid-cols-2">
        <a
            v-for="doc in documents"
            :key="doc.id"
            :href="doc.url"
            class="flex items-center gap-4 rounded-2xl border border-white/10 bg-ink-900/80 p-5 transition hover:border-gold-400/40"
        >
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-gold-400/15 text-xl text-gold-400">
                <Fa icon="file-lines" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-medium text-white">{{ tr(doc.name) }}</span>
                <span class="text-xs text-neutral-400">{{ doc.type }} · {{ formatFileSize(doc.size) }}</span>
            </span>
            <span class="flex items-center gap-2 rounded-lg border border-white/20 px-3 py-2 text-sm">
                <Fa icon="download" /> {{ t('important.download') }}
            </span>
        </a>
        <p v-if="!documents.length" class="rounded-2xl border border-white/10 bg-ink-900/80 p-8 text-center text-neutral-400 md:col-span-2">{{ t('important.empty') }}</p>
    </section>
</template>
