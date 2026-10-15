<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/Front/PageHeader.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

// PDPA: the privacy policy registrants consent to at sign-up (text in Settings › Website content)
const { t, tr } = useI18n();
const site = useSite();
</script>

<template>
    <Head :title="t('privacy.title')" />
    <PageHeader icon="lock" :title="t('privacy.title')" />

    <section class="mx-auto max-w-[1600px] px-4 pb-12 sm:px-6 lg:px-8">
        <div class="max-w-4xl rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6 leading-relaxed whitespace-pre-line text-neutral-200 sm:p-8">{{ tr(site.privacy.body) }}</div>
    </section>
</template>
