<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

// File rules for uploads and the days an author has to answer a journal offer (the dates are set on the Schedule tab's timeline)
const props = defineProps({
    // { maxMb, serverMaxMb, ... }
    settings: { type: Object, required: true },
});

const { t } = useI18n();

const toInput = (s) => ({ max_mb: s.maxMb, journal_answer_days: s.journalAnswerDays });
const form = useForm(toInput(props.settings));
watch(() => props.settings, (value) => {
    form.defaults(toInput(value));
    form.reset();
});

function submit() {
    form.put('/admin/settings/submission', { preserveScroll: true, preserveState: true });
}

const input = 'rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800 dark:scheme-dark';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
</script>

<template>
    <form class="max-w-2xl space-y-8" @submit.prevent="submit">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.submission.intro') }}</p>

        <div>
            <label for="upload-max" class="mb-1.5 block text-sm font-medium">{{ t('admin.settings.submission.maxMb') }}</label>
            <div class="flex items-center gap-3">
                <input
                    id="upload-max"
                    v-model.number="form.max_mb"
                    type="number"
                    min="1"
                    :max="settings.serverMaxMb"
                    :class="[input, 'w-32', border(form.errors.max_mb)]"
                    required
                >
                <span class="text-sm text-neutral-500 dark:text-neutral-400">MB</span>
            </div>
            <Transition name="fade">
                <p v-if="form.errors.max_mb" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ t(form.errors.max_mb, { max: settings.serverMaxMb }) }}</p>
            </Transition>
            <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.settings.submission.serverMax', { max: settings.serverMaxMb }) }}</p>
        </div>

        <div>
            <p class="mb-1.5 text-sm font-medium">{{ t('admin.settings.submission.types') }}</p>
            <div class="flex flex-wrap gap-2">
                <span v-for="type in ['PDF', 'DOC', 'DOCX']" :key="type" class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-600 dark:bg-white/10 dark:text-neutral-300">{{ type }}</span>
            </div>
        </div>

        <div>
            <label for="journal-answer-days" class="mb-1.5 block text-sm font-medium">{{ t('admin.settings.submission.answerDays') }}</label>
            <div class="flex items-center gap-3">
                <input id="journal-answer-days" v-model.number="form.journal_answer_days" type="number" min="1" max="60" :class="[input, 'w-32', border(form.errors.journal_answer_days)]" required>
                <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.submission.days') }}</span>
            </div>
            <p v-if="form.errors.journal_answer_days" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ t(form.errors.journal_answer_days) }}</p>
            <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.submission.answerDaysHint') }}</p>
        </div>

        <button
            type="submit"
            class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
            :disabled="form.processing || !form.isDirty"
        >
            <Fa icon="floppy-disk" /> {{ t('common.save') }}
        </button>
    </form>
</template>
