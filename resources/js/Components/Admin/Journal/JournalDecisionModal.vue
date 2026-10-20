<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

/*
 * The journal's decision from its table (JournalController::select / result): the choice is the button pressed,
 * an optional reason (staff only), then save. The paper page keeps its own form (JournalPanel).
 */
const props = defineProps({
    paper: { type: Object, default: null }, // { id, code, title }
    choice: { type: String, default: null }, // select | decline (step 1) or accepted | rejected (step 2)
});
const emit = defineEmits(['close']);

const { t, tr } = useI18n();

const step = computed(() => (['select', 'decline'].includes(props.choice) ? 'select' : 'result'));
const form = useForm({ comment: '' });
watch(() => props.paper, () => {
    form.reset();
    form.clearErrors();
});

function save() {
    form.transform((data) => ({ [step.value === 'select' ? 'decision' : 'result']: props.choice, comment: data.comment || null }))
        .post(`/admin/journal/${props.paper.id}/${step.value === 'select' ? 'selection' : 'result'}`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
}

const good = computed(() => ['select', 'accepted'].includes(props.choice));
</script>

<template>
    <Modal :show="!!paper" max-width="max-w-lg" @close="emit('close')">
        <form v-if="paper" class="p-6 sm:p-8" @submit.prevent="save">
            <span :class="good ? 'bg-emerald-400/15 text-emerald-400' : 'bg-sky-400/15 text-sky-400'" class="mb-4 grid size-12 place-items-center rounded-xl text-lg">
                <Fa :icon="good ? 'circle-check' : 'arrow-right'" />
            </span>
            <h2 class="text-xl font-semibold">{{ t(`journal.choice.${choice}`) }}</h2>
            <p class="mt-1 text-sm text-neutral-400"><span class="font-mono text-gold-400">{{ paper.code }}</span> · {{ tr(paper.title) }}</p>
            <p class="mt-4 text-sm text-neutral-300">{{ t(`journal.confirm.${choice}`, { code: paper.code }) }}</p>

            <label class="mt-5 block">
                <span class="mb-1.5 block text-sm text-neutral-300">{{ t('journal.panel.reason') }} ({{ t('submissions.form.optional') }})</span>
                <textarea v-model="form.comment" rows="3" maxlength="5000" class="w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm text-white outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20" />
            </label>
            <p v-if="form.errors.comment" class="mt-1 text-xs text-red-400">{{ t(form.errors.comment) }}</p>
            <p class="mt-2 text-xs text-neutral-400"><Fa icon="eye-slash" class="mr-1 text-gold-400" /> {{ t('journal.panel.reasonHidden') }}</p>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing">
                    <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
