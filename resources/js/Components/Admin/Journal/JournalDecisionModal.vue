<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

/*
 * The first-gate decision (SelectionController::route, 2026-11-11), from the Selection table or a paper page:
 * set the track and offer the paper to one partner journal (the author then accepts or declines), or send it to the
 * conference track. An optional note stays with the staff.
 */
const props = defineProps({
    paper: { type: Object, default: null }, // { id, code, title, track, journal }
    choice: { type: String, default: null }, // journal | conference
    tracks: { type: Array, required: true }, // [{ id, name }]
    journals: { type: Array, required: true }, // active partner journals [{ id, name, url, terms }]
    answerDays: { type: Number, required: true }, // days the author has to answer an offer
});
const emit = defineEmits(['close']);

const { t, tr } = useI18n();

const form = useForm({ track_id: null, journal_id: null, comment: '' });
watch(() => props.paper, (paper) => {
    form.clearErrors();
    form.track_id = paper?.track?.id ?? null;
    // Moving an unanswered offer to another journal starts from the current one
    form.journal_id = paper?.journal?.id ?? null;
    form.comment = '';
});

const toJournal = computed(() => props.choice === 'journal');
const picked = computed(() => props.journals.find((j) => j.id === form.journal_id));

function save() {
    form.transform((data) => ({ to: props.choice, track_id: data.track_id, journal_id: toJournal.value ? data.journal_id : null, comment: data.comment || null }))
        .post(`/admin/selection/${props.paper.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
}

const input = 'w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm text-white outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20';
</script>

<template>
    <Modal :show="!!paper" max-width="max-w-xl" @close="emit('close')">
        <form v-if="paper" class="p-6 sm:p-8" @submit.prevent="save">
            <span :class="toJournal ? 'bg-emerald-400/15 text-emerald-400' : 'bg-sky-400/15 text-sky-400'" class="mb-4 grid size-12 place-items-center rounded-xl text-lg">
                <Fa :icon="toJournal ? 'newspaper' : 'arrow-right'" />
            </span>
            <h2 class="text-xl font-semibold">{{ t(`journal.choice.${choice}`) }}</h2>
            <p class="mt-1 text-sm text-neutral-400"><span class="font-mono text-gold-400">{{ paper.code }}</span> · {{ tr(paper.title) }}</p>

            <div class="mt-5 space-y-4">
                <label class="block">
                    <span class="mb-1.5 block text-sm text-neutral-300">{{ t('submissions.fields.track') }}</span>
                    <select v-model="form.track_id" :class="input" required>
                        <option :value="null" disabled>{{ t('submissions.form.chooseTrack') }}</option>
                        <option v-for="track in tracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                    </select>
                    <span v-if="form.errors.track_id" class="mt-1 block text-xs text-red-400">{{ t(form.errors.track_id) }}</span>
                </label>

                <label v-if="toJournal" class="block">
                    <span class="mb-1.5 block text-sm text-neutral-300">{{ t('journal.modal.journal') }}</span>
                    <select v-model="form.journal_id" :class="input" required>
                        <option :value="null" disabled>{{ t('journal.modal.pickJournal') }}</option>
                        <option v-for="journal in journals" :key="journal.id" :value="journal.id">{{ journal.name }}</option>
                    </select>
                    <span v-if="form.errors.journal_id" class="mt-1 block text-xs text-red-400">{{ t(form.errors.journal_id) }}</span>
                    <span v-if="picked?.terms" class="mt-1.5 block text-xs text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-400" />{{ picked.terms }}</span>
                </label>
            </div>

            <p class="mt-4 text-sm text-neutral-300">{{ toJournal ? t('journal.confirm.journal', { days: answerDays }) : t('journal.confirm.conference') }}</p>

            <label class="mt-5 block">
                <span class="mb-1.5 block text-sm text-neutral-300">{{ t('journal.panel.reason') }} ({{ t('submissions.form.optional') }})</span>
                <textarea v-model="form.comment" rows="3" maxlength="5000" :class="input" />
            </label>
            <p v-if="form.errors.comment" class="mt-1 text-xs text-red-400">{{ t(form.errors.comment) }}</p>
            <p class="mt-2 text-xs text-neutral-400"><Fa icon="eye-slash" class="mr-1 text-gold-400" /> {{ t('journal.panel.reasonHidden') }}</p>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60"
                    :disabled="form.processing || !form.track_id || (toJournal && !form.journal_id)"
                >
                    <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
