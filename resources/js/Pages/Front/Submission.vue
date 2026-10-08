<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/Front/PageHeader.vue';
import { useI18n } from '@/i18n';
import { useAuthModal } from '@/composables/useAuthModal';

const { t } = useI18n();
const { openRegister } = useAuthModal();
const isGuest = computed(() => !usePage().props.auth.user);
</script>

<template>
    <Head :title="t('nav.submission')" />
    <PageHeader icon="file-arrow-up" :title="t('submission.title')" :lead="t('submission.lead')" />

    <section class="mx-auto max-w-[1600px] px-4 pb-12 sm:px-6 lg:px-8">
        <h2 class="mb-5 text-xl font-semibold text-gold-400">{{ t('submission.stepsTitle') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="(step, i) in t('submission.steps')" :key="i" class="rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6">
                <span class="mb-4 grid size-11 place-items-center rounded-full bg-gold-400 text-lg font-bold text-black">{{ i + 1 }}</span>
                <h3 class="mb-1 font-semibold text-white">{{ step.title }}</h3>
                <p class="text-sm text-neutral-300">{{ step.text }}</p>
            </div>
        </div>

        <button
            v-if="isGuest"
            class="mt-8 inline-flex items-center gap-2 rounded-lg bg-gold-400 px-6 py-3 font-semibold text-black transition hover:bg-gold-300"
            @click="openRegister"
        >
            <Fa icon="user-plus" /> {{ t('submission.cta') }}
        </button>
    </section>
</template>
