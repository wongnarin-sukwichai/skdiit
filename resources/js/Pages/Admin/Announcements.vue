<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';

/*
 * Announcements (editors and admin): email a group of people, and rewrite the emails the paper workflow sends.
 * Emails go through the configured mailer: written to the log until SMTP is set up, then sent for real.
 */
const props = defineProps({
    counts: { type: Object, required: true }, // people in each group now (App\Support\Audience)
    history: { type: Array, required: true }, // { id, audience, subject, body, recipients, by, at }
    templates: { type: Array, required: true }, // { key, subject_th, subject_en, body_th, body_en, vars, changed }
});

const { t, formatDate, formatNumber } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const tab = ref('send');
const groups = [
    { label: 'registrants', keys: ['registrants', 'attendees', 'authors'] },
    { label: 'authorsTodo', keys: ['authors_revision', 'authors_camera_ready', 'authors_slides'] },
    { label: 'reviewers', keys: ['reviewers', 'reviewers_open', 'reviewers_overdue'] },
];

// ---- Send ----
const form = useForm({ audience: '', subject: '', body: '' });
const confirmOpen = ref(false);
const ready = computed(() => form.audience && props.counts[form.audience] > 0 && form.subject.trim() && form.body.trim());
function send() {
    form.post('/admin/announcements', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onFinish: () => (confirmOpen.value = false),
    });
}
const reading = ref(null);

// ---- Templates ----
// Shown as {name}, {code}, ...
const placeholder = (v) => `{${v}}`;
const fields = ['subject_th', 'subject_en', 'body_th', 'body_en'];
const drafts = reactive({});
const errors = reactive({});
watch(() => props.templates, (list) => list.forEach((tpl) => (drafts[tpl.key] = Object.fromEntries(fields.map((f) => [f, tpl[f]])))), { immediate: true });
const isDirty = (tpl) => fields.some((f) => drafts[tpl.key][f] !== tpl[f]);
const open = ref(null);
function saveTemplate(tpl) {
    router.put(`/admin/announcements/templates/${tpl.key}`, drafts[tpl.key], {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (errors[tpl.key] = {}),
        onError: (e) => (errors[tpl.key] = e),
    });
}
const resetting = ref(null);
function resetTemplate() {
    router.delete(`/admin/announcements/templates/${resetting.value.key}`, { preserveScroll: true, preserveState: true, onFinish: () => (resetting.value = null) });
}

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const label = 'mb-1.5 block text-sm font-medium';
</script>

<template>
    <Head :title="t('admin.menu.announcements')" />

    <Card class="space-y-6">
        <PageHeader icon="bullhorn" :title="t('admin.menu.announcements')" :subtitle="t('announcements.subtitle')" />
        <p class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-400/10 dark:text-amber-200">
            <Fa icon="circle-info" class="mt-0.5" /> {{ t('announcements.noSmtp') }}
        </p>

        <nav class="flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-white/10" role="tablist">
            <button
                v-for="key in ['send', 'templates']"
                :key="key"
                role="tab"
                :aria-selected="tab === key"
                :class="tab === key ? 'border-gold-400 text-gold-600 dark:text-gold-400' : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                @click="tab = key"
            >
                <Fa :icon="key === 'send' ? 'paper-plane' : 'envelope-open-text'" /> {{ t(`announcements.tabs.${key}`) }}
            </button>
        </nav>

        <Transition name="fade" mode="out-in">
            <!-- Send to a group -->
            <div v-if="tab === 'send'" key="send" class="grid gap-6 xl:grid-cols-[1.3fr_1fr]">
                <form class="space-y-4" @submit.prevent="confirmOpen = true">
                    <div>
                        <p :class="label">{{ t('announcements.audience') }}</p>
                        <div class="space-y-3">
                            <div v-for="group in groups" :key="group.label">
                                <p class="mb-1 text-xs font-semibold text-neutral-500 dark:text-neutral-400">{{ t(`announcements.group.${group.label}`) }}</p>
                                <div class="grid gap-2 sm:grid-cols-3" role="radiogroup">
                                    <button
                                        v-for="key in group.keys"
                                        :key="key"
                                        type="button"
                                        role="radio"
                                        :aria-checked="form.audience === key"
                                        :disabled="!counts[key]"
                                        :class="form.audience === key ? 'border-gold-400 bg-gold-50 dark:bg-gold-400/10' : 'border-neutral-300 hover:border-gold-400 dark:border-white/15'"
                                        class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm transition disabled:opacity-40 disabled:hover:border-neutral-300"
                                        @click="form.audience = key"
                                    >
                                        <span>{{ t(`announcements.audiences.${key}`) }}</span>
                                        <span class="rounded-full bg-neutral-100 px-2 text-xs tabular-nums dark:bg-white/10">{{ formatNumber(counts[key]) }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <span v-if="form.errors.audience" class="mt-1 block text-xs text-red-500">{{ t(form.errors.audience) }}</span>
                    </div>
                    <label class="block">
                        <span :class="label">{{ t('announcements.subject') }}</span>
                        <input v-model="form.subject" maxlength="200" :class="[input, border(form.errors.subject)]">
                    </label>
                    <label class="block">
                        <span :class="label">{{ t('announcements.body') }}</span>
                        <textarea v-model="form.body" rows="10" maxlength="20000" :placeholder="t('announcements.bodyPlaceholder')" :class="[input, border(form.errors.body)]" />
                        <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('announcements.nameHint') }}</span>
                    </label>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400" :disabled="!ready || form.processing">
                        <Fa icon="paper-plane" /> {{ form.audience ? t('announcements.sendTo', { n: formatNumber(counts[form.audience]) }) : t('announcements.send') }}
                    </button>
                </form>

                <!-- History -->
                <section>
                    <h2 class="mb-3 flex items-center gap-2 font-semibold"><Fa icon="clock-rotate-left" class="text-gold-500" /> {{ t('announcements.history') }}</h2>
                    <ul class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                        <li v-for="item in history" :key="item.id">
                            <button type="button" class="block w-full p-3 text-left transition hover:bg-gold-50 dark:hover:bg-gold-400/5" @click="reading = item">
                                <span class="block truncate text-sm font-medium">{{ item.subject }}</span>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ t(`announcements.audiences.${item.audience}`) }} · {{ t('announcements.people', { n: formatNumber(item.recipients) }) }} · {{ item.by ?? '—' }} · {{ when(item.at) }}
                                </span>
                            </button>
                        </li>
                        <li v-if="!history.length" class="p-6 text-center text-sm text-neutral-500 dark:text-neutral-400">{{ t('announcements.noHistory') }}</li>
                    </ul>
                </section>
            </div>

            <!-- Workflow email templates -->
            <div v-else key="templates" class="space-y-3">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('announcements.templatesIntro') }}</p>
                <section v-for="tpl in templates" :key="tpl.key" class="rounded-xl border border-neutral-200 dark:border-white/10">
                    <button type="button" class="flex w-full items-center gap-3 p-3 text-left" @click="open = open === tpl.key ? null : tpl.key">
                        <Fa icon="envelope" class="text-gold-500" />
                        <span class="flex-1">
                            <span class="block text-sm font-medium">{{ t(`announcements.template.${tpl.key}`) }}</span>
                            <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">{{ tpl.subject_th }}</span>
                        </span>
                        <span v-if="tpl.changed" class="text-xs text-gold-700 dark:text-gold-300">{{ t('announcements.changedBy', { name: tpl.changed.by ?? '—', date: when(tpl.changed.at) }) }}</span>
                        <Fa :icon="open === tpl.key ? 'chevron-up' : 'chevron-down'" class="text-xs text-neutral-400" />
                    </button>
                    <Transition name="fade">
                        <form v-if="open === tpl.key" class="space-y-3 border-t border-neutral-200 p-3 dark:border-white/10" @submit.prevent="saveTemplate(tpl)">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ t('announcements.vars') }}
                                <code v-for="v in tpl.vars" :key="v" class="mx-0.5 rounded bg-neutral-100 px-1.5 py-0.5 text-gold-700 dark:bg-white/10 dark:text-gold-300">{{ placeholder(v) }}</code>
                            </p>
                            <div class="grid gap-3 md:grid-cols-2">
                                <label v-for="lang in ['th', 'en']" :key="lang" class="block space-y-2">
                                    <span class="block text-xs font-semibold text-neutral-500 dark:text-neutral-400">{{ t(`lang.${lang}`) }}</span>
                                    <input v-model="drafts[tpl.key][`subject_${lang}`]" maxlength="200" :aria-label="t('announcements.subject')" :class="[input, border(errors[tpl.key]?.[`subject_${lang}`])]">
                                    <textarea v-model="drafts[tpl.key][`body_${lang}`]" rows="8" maxlength="10000" :aria-label="t('announcements.body')" :class="[input, border(errors[tpl.key]?.[`body_${lang}`])]" />
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40" :disabled="!isDirty(tpl)">
                                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                                </button>
                                <button v-if="tpl.changed" type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm transition hover:border-gold-400 dark:border-white/15" @click="resetting = tpl">
                                    <Fa icon="arrow-rotate-right" /> {{ t('announcements.reset') }}
                                </button>
                            </div>
                        </form>
                    </Transition>
                </section>
            </div>
        </Transition>

        <!-- Confirm sending -->
        <Modal :show="confirmOpen" @close="confirmOpen = false">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400"><Fa icon="bullhorn" /></span>
                <h2 class="text-xl font-semibold">{{ t('announcements.confirmTitle') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ form.audience ? t('announcements.confirmText', { n: formatNumber(counts[form.audience]), group: t(`announcements.audiences.${form.audience}`) }) : '' }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirmOpen = false">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing" @click="send">
                        <Fa icon="paper-plane" /> {{ t('announcements.send') }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- An announcement already sent -->
        <Modal :show="!!reading" @close="reading = null">
            <div v-if="reading" class="p-6 sm:p-8">
                <p class="text-xs text-neutral-400">{{ t(`announcements.audiences.${reading.audience}`) }} · {{ t('announcements.people', { n: formatNumber(reading.recipients) }) }} · {{ reading.by ?? '—' }} · {{ when(reading.at) }}</p>
                <h2 class="mt-1 text-xl font-semibold">{{ reading.subject }}</h2>
                <p class="mt-4 max-h-[60vh] overflow-y-auto text-sm whitespace-pre-line text-neutral-200">{{ reading.body }}</p>
            </div>
        </Modal>

        <ConfirmModal
            :show="!!resetting"
            icon="arrow-rotate-right"
            :title="t('announcements.reset')"
            :message="t('announcements.resetText')"
            :confirm-label="t('announcements.reset')"
            @close="resetting = null"
            @confirm="resetTemplate"
        />
    </Card>
</template>
