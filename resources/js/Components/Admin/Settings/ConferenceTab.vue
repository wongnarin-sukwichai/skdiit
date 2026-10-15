<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BilingualInput from '@/Components/Admin/Settings/BilingualInput.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

// Conference information shown on the public site and in emails (SiteContentController::updateConference)
const { t } = useI18n();
const site = useSite();

const toForm = (c) => ({ shortName: c.shortName, fullName: { ...c.fullName }, date: c.date, venue: { ...c.venue }, email: c.email, phone: c.phone });
const form = useForm(toForm(site.value.conference));
watch(() => site.value.conference, (c) => {
    form.defaults(toForm(c));
    form.reset();
});

function submit() {
    form.put('/admin/settings/site/conference', { preserveScroll: true, preserveState: true });
}

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800 dark:[color-scheme:dark]';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const errorText = 'mt-1 block text-xs text-red-500 dark:text-red-400';
</script>

<template>
    <form class="max-w-4xl space-y-6" @submit.prevent="submit">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.conference.intro') }}</p>

        <div class="grid gap-6 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium">{{ t('admin.conference.shortName') }}</span>
                <input v-model="form.shortName" maxlength="40" :class="[input, border(form.errors.shortName)]">
                <span v-if="form.errors.shortName" :class="errorText">{{ t(form.errors.shortName) }}</span>
                <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.conference.shortNameHint') }}</span>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium">{{ t('admin.conference.date') }}</span>
                <input v-model="form.date" type="date" :class="[input, border(form.errors.date)]">
                <span v-if="form.errors.date" :class="errorText">{{ t(form.errors.date) }}</span>
            </label>
        </div>

        <BilingualInput v-model="form.fullName" field="fullName" :label="t('admin.conference.fullName')" :errors="form.errors" />
        <BilingualInput v-model="form.venue" field="venue" :label="t('admin.conference.venue')" :errors="form.errors" />

        <div class="grid gap-6 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium">{{ t('admin.conference.email') }}</span>
                <input v-model="form.email" type="email" :class="[input, border(form.errors.email)]">
                <span v-if="form.errors.email" :class="errorText">{{ t(form.errors.email) }}</span>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium">{{ t('admin.conference.phone') }}</span>
                <input v-model="form.phone" type="tel" maxlength="50" :class="[input, border(form.errors.phone)]">
                <span v-if="form.errors.phone" :class="errorText">{{ t(form.errors.phone) }}</span>
            </label>
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
