<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({
    settings: { type: Object, required: true }, // { maxMb, serverMaxMb, opens, deadline: 'Y-m-d H:i' | null }
});

const { t } = useI18n();

// <input type="datetime-local"> works with 'Y-m-dTH:i'
const toInput = (s) => ({ max_mb: s.maxMb, opens: s.opens?.replace(' ', 'T') ?? '', deadline: s.deadline?.replace(' ', 'T') ?? '' });
const form = useForm(toInput(props.settings));
watch(() => props.settings, (value) => {
    form.defaults(toInput(value));
    form.reset();
});

function submit() {
    form.transform((data) => ({ ...data, opens: data.opens || null, deadline: data.deadline || null }))
        .put('/admin/settings/submission', { preserveScroll: true, preserveState: true });
}

const input = 'rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800 dark:[color-scheme:dark]';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const hint = 'mt-2 text-xs text-neutral-500 dark:text-neutral-400';
</script>

<template>
    <form class="max-w-2xl space-y-8" @submit.prevent="submit">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.submission.intro') }}</p>

        <!-- Submission period: before or after it the author gets an alert instead of the form -->
        <div class="grid gap-6 sm:grid-cols-2">
            <div v-for="field in ['opens', 'deadline']" :key="field">
                <label :for="field" class="mb-1.5 block text-sm font-medium">{{ t(`admin.settings.submission.${field}`) }}</label>
                <input
                    :id="field"
                    v-model="form[field]"
                    type="datetime-local"
                    :min="field === 'deadline' ? form.opens || undefined : undefined"
                    :max="field === 'opens' ? form.deadline || undefined : undefined"
                    :class="[input, 'w-full', border(form.errors[field])]"
                >
                <Transition name="fade">
                    <p v-if="form.errors[field]" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ t(form.errors[field]) }}</p>
                </Transition>
                <button v-if="form[field]" type="button" class="mt-1.5 text-xs text-neutral-500 hover:text-red-500 dark:text-neutral-400" @click="form[field] = ''">
                    <Fa icon="xmark" /> {{ t(`admin.settings.submission.clear.${field}`) }}
                </button>
            </div>
            <p class="-mt-3 text-xs text-neutral-500 sm:col-span-2 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.settings.submission.periodHint') }}</p>
        </div>

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
            <p :class="hint"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.settings.submission.serverMax', { max: settings.serverMaxMb }) }}</p>
        </div>

        <div>
            <p class="mb-1.5 text-sm font-medium">{{ t('admin.settings.submission.types') }}</p>
            <div class="flex flex-wrap gap-2">
                <span v-for="type in ['PDF', 'DOC', 'DOCX']" :key="type" class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-600 dark:bg-white/10 dark:text-neutral-300">{{ type }}</span>
            </div>
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
