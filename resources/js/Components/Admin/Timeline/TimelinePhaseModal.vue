<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';
import { addDays, spanDays } from '@/utils/timeline';

/*
 * Edit one timeline row. Rows the system enforces (phase.real) save their real setting straight away:
 *   submission → opens + deadline, journalSelect → journal_deadline, slides → slides_cutoff_days (SettingsController::updateDeadlines),
 *   conference → the conference date (SiteContentController::updateConference).
 * Every other row is still an assumed date: "apply" only redraws the timeline (ScheduleTab keeps it in memory).
 */
const props = defineProps({
    phase: { type: Object, default: null }, // { key, label, start, end, mode, milestone?, perItem?, public?, real?, source? }
    issues: { type: Array, default: () => [] }, // this phase's problems, already worded
    settings: { type: Object, required: true }, // SettingsController::index `submission`
});
const emit = defineEmits(['close', 'apply']);

const { t, formatDate } = useI18n();
const site = useSite();

const form = reactive({ start: '', end: '', mode: 'info', days: null, public: false, opens: '', deadline: '', journalDeadline: '', cutoff: '' });
const errors = ref({});
const saving = ref(false);

watch(() => props.phase, (phase) => {
    if (!phase) return;
    const s = props.settings;
    errors.value = {};
    Object.assign(form, {
        start: phase.start,
        end: phase.end,
        mode: phase.mode,
        days: phase.perItem?.days ?? null,
        public: !!phase.public,
        // <input type="datetime-local"> works with 'Y-m-dTH:i'
        opens: s.opens?.replace(' ', 'T') ?? '',
        deadline: s.deadline?.replace(' ', 'T') ?? '',
        journalDeadline: s.journalDeadline ?? '',
        cutoff: s.slidesCutoffDays ?? '',
    });
}, { immediate: true });

// Slides close this many days before each paper's own talk; the timeline row shows it against the conference day
const slidesClose = computed(() => (form.cutoff === '' || form.cutoff === null ? site.value.conference.date : addDays(site.value.conference.date, -Number(form.cutoff))));

function save() {
    const request = {
        submission: ['/admin/settings/deadlines', { opens: form.opens || null, deadline: form.deadline || null }],
        journalSelect: ['/admin/settings/deadlines', { journal_deadline: form.journalDeadline || null }],
        slides: ['/admin/settings/deadlines', { slides_cutoff_days: form.cutoff === '' ? null : form.cutoff }],
        conference: ['/admin/settings/site/conference', { ...site.value.conference, date: form.start }],
    }[props.phase.key];

    saving.value = true;
    router.put(request[0], request[1], {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => emit('close'),
        onError: (e) => { errors.value = e; },
        onFinish: () => { saving.value = false; },
    });
}

function apply() {
    const end = props.phase.milestone ? form.start : form.end;
    emit('apply', {
        key: props.phase.key,
        start: form.start,
        end,
        mode: form.mode,
        public: form.public,
        ...(props.phase.perItem ? { perItem: { ...props.phase.perItem, days: Math.max(1, Number(form.days) || 1) } } : {}),
    });
}

const submit = () => (props.phase.real ? save() : apply());
const error = (key) => (errors.value[key] ? t(errors.value[key]) : null);
const clearBtn = 'mt-1.5 text-xs text-neutral-400 hover:text-red-400';
</script>

<template>
    <Modal :show="!!phase" max-width="max-w-lg" @close="emit('close')">
        <form v-if="phase" class="p-6 sm:p-8" @submit.prevent="submit">
            <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400"><Fa :icon="phase.icon" /></span>
            <h2 class="text-2xl font-semibold">{{ phase.label }}</h2>
            <p class="mt-1 mb-6 text-sm text-neutral-400">{{ t(`admin.timeline.who.${phase.who}`) }} · {{ t(`admin.timeline.desc.${phase.key}`) }}</p>

            <p
                :class="phase.real ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-amber-400/30 bg-amber-400/10 text-amber-200'"
                class="mb-5 flex gap-2 rounded-lg border p-3 text-xs"
            >
                <Fa :icon="phase.real ? 'circle-check' : 'flask'" class="mt-0.5 shrink-0" />
                <span>
                    {{ t(phase.real ? `admin.timeline.source.${phase.key}` : 'admin.timeline.source.assumed') }}
                    <template v-if="phase.source === 'unset'">{{ t('admin.timeline.source.unset') }}</template>
                </span>
            </p>

            <!-- Rows the system enforces: their real settings -->
            <div v-if="phase.key === 'submission' && phase.real" class="grid gap-4 sm:grid-cols-2">
                <FormField v-for="field in ['opens', 'deadline']" :key="field" :label="t(`admin.settings.submission.${field}`)" :error="error(field)" v-slot="{ inputClass }">
                    <input
                        v-model="form[field]"
                        type="datetime-local"
                        :min="field === 'deadline' ? form.opens || undefined : undefined"
                        :max="field === 'opens' ? form.deadline || undefined : undefined"
                        :class="[inputClass, 'scheme-dark']"
                    >
                    <button v-if="form[field]" type="button" :class="clearBtn" @click="form[field] = ''"><Fa icon="xmark" /> {{ t(`admin.settings.submission.clear.${field}`) }}</button>
                </FormField>
                <p class="-mt-1 text-xs text-neutral-400 sm:col-span-2">{{ t('admin.settings.submission.periodHint') }}</p>
            </div>

            <div v-else-if="phase.key === 'journalSelect' && phase.real" class="space-y-4">
                <p class="text-sm text-neutral-300">{{ t('admin.timeline.journalStarts', { date: formatDate(phase.start) }) }}</p>
                <FormField :label="t('admin.settings.submission.journalDeadline')" icon="calendar-days" :error="error('journal_deadline')" v-slot="{ inputClass }">
                    <input v-model="form.journalDeadline" type="date" :min="phase.start" :class="[inputClass, 'scheme-dark']">
                </FormField>
                <p v-if="form.journalDeadline && form.journalDeadline >= phase.start" class="-mt-2 text-xs text-neutral-400">{{ t('admin.timeline.journalDays', { days: spanDays({ start: phase.start, end: form.journalDeadline }) }) }}</p>
                <p class="text-xs text-neutral-400">{{ t('admin.settings.submission.journalDeadlineHint') }}</p>
            </div>

            <div v-else-if="phase.key === 'slides' && phase.real" class="space-y-4">
                <FormField :label="t('admin.settings.submission.slidesCutoff')" icon="hourglass-half" :error="error('slides_cutoff_days')" v-slot="{ inputClass }">
                    <input v-model="form.cutoff" type="number" min="0" max="60" :placeholder="t('admin.timeline.untilTalk')" :class="inputClass">
                </FormField>
                <p class="text-xs text-neutral-400">{{ t('admin.timeline.slidesHint', { date: formatDate(slidesClose) }) }}</p>
            </div>

            <div v-else-if="phase.key === 'conference' && phase.real" class="space-y-4">
                <FormField :label="t('admin.timeline.date')" icon="calendar-days" :error="error('date')" v-slot="{ inputClass }">
                    <input v-model="form.start" type="date" required :class="[inputClass, 'scheme-dark']">
                </FormField>
            </div>

            <!-- Assumed rows: a trial on the timeline only -->
            <div v-else class="grid gap-4 sm:grid-cols-2">
                <FormField :class="phase.milestone ? 'sm:col-span-2' : ''" :label="t(phase.milestone ? 'admin.timeline.date' : 'admin.timeline.start')" icon="calendar-days" v-slot="{ inputClass }">
                    <input v-model="form.start" type="date" required :class="[inputClass, 'scheme-dark']">
                </FormField>
                <FormField v-if="!phase.milestone" :label="t('admin.timeline.end')" icon="calendar-days" v-slot="{ inputClass }">
                    <input v-model="form.end" type="date" required :min="form.start" :class="[inputClass, 'scheme-dark']">
                </FormField>
                <p v-if="!phase.milestone && form.start && form.end && form.start <= form.end" class="-mt-2 text-xs text-neutral-400 sm:col-span-2">
                    {{ t('admin.timeline.spanDays', { days: spanDays(form) }) }}
                </p>

                <FormField class="sm:col-span-2" :label="t('admin.timeline.whenLate')" icon="lock" v-slot="{ inputClass }">
                    <select v-model="form.mode" :class="inputClass">
                        <option v-for="mode in ['lock', 'warn', 'info']" :key="mode" :value="mode">{{ t(`admin.timeline.modeLong.${mode}`) }}</option>
                    </select>
                </FormField>

                <FormField v-if="phase.perItem" class="sm:col-span-2" :label="t(`admin.timeline.perItem.${phase.perItem.unit}`)" icon="hourglass-half" v-slot="{ inputClass }">
                    <input v-model.number="form.days" type="number" min="1" max="90" required :class="inputClass">
                </FormField>

                <label class="flex items-center gap-2 text-sm text-neutral-300 sm:col-span-2">
                    <input v-model="form.public" type="checkbox" class="size-4 accent-gold-400">
                    <Fa icon="globe" class="text-gold-400" /> {{ t('admin.timeline.public') }}
                </label>
            </div>

            <p v-if="phase.real" class="mt-4 text-xs text-neutral-400"><Fa icon="lock" class="mr-1 text-gold-400" /> {{ t('admin.timeline.fixedMode', { mode: t(`admin.timeline.modeLong.${phase.mode}`) }) }}</p>

            <ul v-if="issues.length" class="mt-5 space-y-1.5">
                <li v-for="(issue, i) in issues" :key="i" :class="issue.level === 'error' ? 'text-red-400' : 'text-amber-400'" class="flex gap-2 text-xs">
                    <Fa :icon="issue.level === 'error' ? 'circle-exclamation' : 'triangle-exclamation'" class="mt-0.5 shrink-0" /> {{ issue.text }}
                </li>
            </ul>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="saving">
                    <Fa :icon="phase.real ? 'floppy-disk' : 'check'" /> {{ phase.real ? t('common.save') : t('admin.timeline.apply') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
