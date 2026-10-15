<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

/*
 * The journal's first gate on a paper page (JournalController::panel): its decisions so far with the reasons
 * (staff only), and the open step for the journal or admin. Editors see it without buttons.
 */
const props = defineProps({
    submission: { type: Object, required: true },
    journal: { type: Object, required: true }, // { canAct, canSelect, canResult, deadline, pastDeadline, url, decisions }
    deadlinePassed: { type: Boolean, required: true }, // the submission deadline (the journal selects after it)
});

const { t, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

// Only papers that are or were with the journal show the panel
const visible = computed(() => ['submitted', 'journal_selected', 'journal_accepted'].includes(props.submission.status) || props.journal.decisions.length);

// Step 1 (selection) or step 2 (the journal's final answer): same form, different choices and endpoint
const step = computed(() => (props.journal.canSelect ? 'select' : props.journal.canResult ? 'result' : null));
const choices = computed(() => (step.value === 'select'
    ? [{ value: 'select', icon: 'circle-check', tone: 'good' }, { value: 'decline', icon: 'arrow-right', tone: 'other' }]
    : [{ value: 'accepted', icon: 'circle-check', tone: 'good' }, { value: 'rejected', icon: 'arrow-right', tone: 'other' }]));
const form = useForm({ choice: '', comment: '' });
const confirmOpen = ref(false);

function save() {
    const url = `/admin/journal/${props.submission.id}/${step.value === 'select' ? 'selection' : 'result'}`;
    form.transform((data) => ({ [step.value === 'select' ? 'decision' : 'result']: data.choice, comment: data.comment }))
        .post(url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onFinish: () => (confirmOpen.value = false),
        });
}

const toneActive = {
    good: 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    other: 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
};
const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
</script>

<template>
    <section v-if="visible" class="space-y-4 rounded-xl border border-emerald-300 p-4 dark:border-emerald-400/30">
        <h2 class="flex items-center gap-2 font-semibold"><Fa icon="newspaper" class="text-emerald-600 dark:text-emerald-400" /> {{ t('journal.panel.title') }}</h2>

        <!-- Admin's warning: the journal's deadline has passed and it has not selected yet -->
        <p v-if="journal.pastDeadline" class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
            <Fa icon="triangle-exclamation" class="mt-0.5" /> {{ t('journal.panel.pastDeadline', { date: formatDate(journal.deadline) }) }}
        </p>

        <!-- Decisions so far, with the journal's reasons (never shown to the author) -->
        <ul v-if="journal.decisions.length" class="space-y-2">
            <li v-for="item in journal.decisions" :key="item.id" class="rounded-lg bg-neutral-50 px-3 py-2 text-sm dark:bg-white/5">
                <p class="font-medium">{{ t(`journal.decision.${item.action}.${item.toStatus}`) }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ item.actor?.name ?? '—' }} · {{ when(item.at) }}</p>
                <p v-if="item.comment" class="mt-1 whitespace-pre-line text-neutral-700 dark:text-neutral-200">{{ item.comment }}</p>
            </li>
        </ul>

        <!-- Open step for the journal (or admin) -->
        <form v-if="step" class="space-y-3" @submit.prevent="confirmOpen = true">
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ t(`journal.panel.${step}Intro`) }}</p>
            <div class="grid gap-2 sm:grid-cols-2" role="radiogroup">
                <button
                    v-for="option in choices"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="form.choice === option.value"
                    :class="form.choice === option.value ? toneActive[option.tone] : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                    class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                    @click="form.choice = option.value"
                >
                    <Fa :icon="option.icon" /> {{ t(`journal.choice.${option.value}`) }}
                </button>
            </div>
            <label class="block">
                <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('journal.panel.reason') }} ({{ t('submissions.form.optional') }})</span>
                <textarea v-model="form.comment" rows="3" maxlength="5000" :class="input" />
            </label>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="eye-slash" class="mr-1 text-gold-500" /> {{ t('journal.panel.reasonHidden') }}</p>
            <button
                type="submit"
                class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                :disabled="!form.choice || form.processing"
            >
                <Fa icon="floppy-disk" /> {{ t('submissions.screening.save') }}
            </button>
        </form>

        <!-- Nothing to press: say why -->
        <p v-else-if="submission.status === 'submitted' && !deadlinePassed" class="text-sm text-neutral-500 dark:text-neutral-400">
            <Fa icon="lock" class="mr-1" /> {{ t('journal.panel.afterDeadline') }}
        </p>
        <p v-else-if="!journal.canAct && ['submitted', 'journal_selected'].includes(submission.status)" class="text-sm text-neutral-500 dark:text-neutral-400">
            <Fa icon="clock" class="mr-1" /> {{ t(`journal.panel.waiting.${submission.status}`) }}
        </p>
        <p v-else-if="submission.status === 'journal_accepted'" class="text-sm text-emerald-700 dark:text-emerald-300">
            <Fa icon="circle-check" class="mr-1" /> {{ t('journal.panel.acceptedNote') }}
        </p>

        <Modal :show="confirmOpen" @close="confirmOpen = false">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="newspaper" />
                </span>
                <h2 class="text-xl font-semibold">{{ t('journal.confirm.title') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ form.choice ? t(`journal.confirm.${form.choice}`, { code: submission.code }) : '' }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirmOpen = false">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing" @click="save">
                        <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
