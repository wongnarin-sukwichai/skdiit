<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

// Settings › Conference year: start a new year (ConferenceYearController::rollover) and download a backup
const props = defineProps({
    summary: { type: Object, required: true }, // { year, papers, toArchive, unfinished, registrants, archived }
});

const { t, formatNumber } = useI18n();

const form = useForm({ year: props.summary.year + 1, confirm: '' });
const confirmOpen = ref(false);
const typedOk = computed(() => String(form.confirm).trim() === String(form.year));
function start() {
    form.post('/admin/settings/year/rollover', {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;
            form.reset('confirm');
        },
    });
}

const stat = 'rounded-xl border border-neutral-200 p-4 dark:border-white/10';
const input = 'rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
</script>

<template>
    <div class="max-w-4xl space-y-8">
        <!-- New conference year -->
        <section class="space-y-4">
            <h3 class="flex items-center gap-2 font-semibold"><Fa icon="calendar-days" class="text-gold-500" /> {{ t('admin.year.title', { year: summary.year }) }}</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="key in ['papers', 'toArchive', 'unfinished', 'registrants']" :key="key" :class="stat">
                    <p class="text-2xl font-semibold tabular-nums">{{ formatNumber(summary[key]) }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t(`admin.year.stat.${key}`) }}</p>
                </div>
            </div>
            <ul class="space-y-1.5 text-sm text-neutral-600 dark:text-neutral-300">
                <li v-for="i in 5" :key="i" class="flex gap-2"><Fa icon="circle-check" class="mt-1 shrink-0 text-gold-500" /> {{ t(`admin.year.what.${i}`) }}</li>
            </ul>
            <p v-if="summary.unfinished" class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-400/10 dark:text-amber-200">
                <Fa icon="triangle-exclamation" class="mt-0.5" /> {{ t('admin.year.unfinishedWarning', { n: formatNumber(summary.unfinished) }) }}
            </p>
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium">{{ t('admin.year.newYear') }}</span>
                    <input v-model.number="form.year" type="number" :min="summary.year + 1" :max="summary.year + 5" :class="[input, 'w-32']">
                </label>
                <button type="button" class="flex items-center gap-2 rounded-lg border border-red-400 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10" @click="confirmOpen = true">
                    <Fa icon="rotate" /> {{ t('admin.year.start', { year: form.year }) }}
                </button>
            </div>
            <p v-if="form.errors.year" class="text-xs text-red-500">{{ t(form.errors.year) }}</p>
        </section>

        <!-- Backup -->
        <section class="space-y-3 border-t border-neutral-200 pt-6 dark:border-white/10">
            <h3 class="flex items-center gap-2 font-semibold"><Fa icon="database" class="text-gold-500" /> {{ t('admin.year.backup') }}</h3>
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ t('admin.year.backupText') }}</p>
            <a href="/admin/settings/year/backup" class="inline-flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                <Fa icon="download" /> {{ t('admin.year.backupDownload') }}
            </a>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.year.backupHint') }}</p>
        </section>

        <Modal :show="confirmOpen" @close="confirmOpen = false">
            <form class="p-6 sm:p-8" @submit.prevent="start">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-red-500/15 text-lg text-red-400"><Fa icon="rotate" /></span>
                <h2 class="text-xl font-semibold">{{ t('admin.year.confirmTitle', { year: form.year }) }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ t('admin.year.confirmText', { year: summary.year, n: formatNumber(summary.toArchive) }) }}</p>
                <label class="mt-4 block">
                    <span class="mb-1.5 block text-sm">{{ t('admin.year.typeYear', { year: form.year }) }}</span>
                    <input v-model="form.confirm" inputmode="numeric" class="w-40 rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400">
                </label>
                <p v-if="form.errors.confirm" class="mt-1 text-xs text-red-400">{{ t(form.errors.confirm) }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirmOpen = false">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400 disabled:opacity-50" :disabled="!typedOk || form.processing">
                        <Fa icon="rotate" /> {{ t('admin.year.start', { year: form.year }) }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
