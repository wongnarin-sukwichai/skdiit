<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

// Editor's decision after peer review, or on a revised paper (decide directly or start a new review round)
const props = defineProps({
    submission: { type: Object, required: true },
    canDecide: { type: Boolean, required: true },
    canStartRound: { type: Boolean, required: true },
});

const { t, formatDate } = useI18n();

const isoDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const today = isoDate(new Date());

const decisions = [
    { value: 'accept', icon: 'circle-check', active: 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
    { value: 'minor', icon: 'pen', active: 'border-gold-500 bg-gold-50 text-gold-700 dark:bg-gold-500/15 dark:text-gold-300' },
    { value: 'major', icon: 'pen-to-square', active: 'border-orange-500 bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300' },
    { value: 'reject', icon: 'xmark', active: 'border-red-500 bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300' },
];

const form = useForm({ decision: '', comment: '', revision_due_date: isoDate(new Date(Date.now() + 14 * 86400000)) });
const needsDue = computed(() => ['minor', 'major'].includes(form.decision));

const confirm = ref(null); // 'decision' | 'round'
const roundBusy = ref(false);
function save() {
    form.transform((data) => ({ ...data, revision_due_date: needsDue.value ? data.revision_due_date : null }))
        .post(`/admin/submissions/${props.submission.id}/decision`, {
            preserveScroll: true,
            onSuccess: () => form.reset('decision', 'comment'),
            onFinish: () => (confirm.value = null),
        });
}
function startRound() {
    router.post(`/admin/submissions/${props.submission.id}/review-round`, {}, {
        preserveScroll: true,
        onStart: () => (roundBusy.value = true),
        onFinish: () => {
            roundBusy.value = false;
            confirm.value = null;
        },
    });
}

const input = 'rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800 dark:[color-scheme:dark]';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
</script>

<template>
    <section v-if="canDecide || canStartRound" class="rounded-xl border border-gold-300 p-4 dark:border-gold-400/30">
        <h2 class="mb-1 flex items-center gap-2 font-semibold"><Fa icon="gavel" class="text-gold-400" /> {{ t('decision.title') }}</h2>
        <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">
            {{ submission.status === 'revised' ? t('decision.revisedIntro') : t('decision.intro') }}
        </p>

        <!-- Revised paper: another review round instead of a direct decision -->
        <div v-if="canStartRound" class="mb-5 flex flex-wrap items-center gap-3 rounded-lg bg-violet-50 px-4 py-3 text-sm dark:bg-violet-500/10">
            <Fa icon="arrow-rotate-right" class="text-violet-600 dark:text-violet-300" />
            <span class="flex-1">{{ t('decision.roundHint', { round: submission.reviewRound + 1 }) }}</span>
            <button class="flex items-center gap-2 rounded-lg border border-violet-400 px-4 py-2 font-medium text-violet-700 transition hover:bg-violet-100 dark:text-violet-200 dark:hover:bg-violet-500/20" @click="confirm = 'round'">
                <Fa icon="user-check" /> {{ t('decision.startRound') }}
            </button>
        </div>

        <form class="space-y-4" @submit.prevent="confirm = 'decision'">
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4" role="radiogroup">
                <button
                    v-for="option in decisions"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="form.decision === option.value"
                    :class="form.decision === option.value ? option.active : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                    class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                    @click="form.decision = option.value"
                >
                    <Fa :icon="option.icon" /> {{ t(`reviews.recommendation.${option.value}`) }}
                </button>
            </div>
            <span v-if="form.errors.decision" class="block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.decision) }}</span>

            <Transition name="fade">
                <label v-if="needsDue" class="block">
                    <span class="mb-1 block text-sm font-medium">{{ t('decision.revisionDue') }} <span class="text-red-500">*</span></span>
                    <input v-model="form.revision_due_date" type="date" :min="today" :class="[input, border(form.errors.revision_due_date)]" required>
                    <span v-if="form.errors.revision_due_date" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.revision_due_date) }}</span>
                </label>
            </Transition>

            <label class="block">
                <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.screening.comment') }} ({{ t('submissions.form.optional') }})</span>
                <textarea v-model="form.comment" rows="4" maxlength="5000" :placeholder="t('decision.commentPlaceholder')" :class="[input, border(form.errors.comment), 'w-full']" />
            </label>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('decision.note') }}</p>
            <button
                type="submit"
                class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                :disabled="!form.decision || (needsDue && !form.revision_due_date) || form.processing"
            >
                <Fa icon="floppy-disk" /> {{ t('submissions.screening.save') }}
            </button>
        </form>

        <Modal :show="!!confirm" @close="confirm = null">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa :icon="confirm === 'round' ? 'user-check' : 'gavel'" />
                </span>
                <template v-if="confirm === 'round'">
                    <h2 class="text-xl font-semibold">{{ t('decision.confirmRoundTitle') }}</h2>
                    <p class="mt-2 text-sm text-neutral-400">{{ t('decision.confirmRoundText', { code: submission.code, round: submission.reviewRound + 1 }) }}</p>
                </template>
                <template v-else>
                    <h2 class="text-xl font-semibold">{{ t('decision.confirmTitle') }}</h2>
                    <p class="mt-2 text-sm text-neutral-400">
                        {{ t('submissions.screening.confirmText', { code: submission.code, decision: form.decision ? t(`reviews.recommendation.${form.decision}`) : '' }) }}
                        <template v-if="needsDue"> {{ t('decision.confirmDue', { date: formatDate(form.revision_due_date, { day: 'numeric', month: 'long', year: 'numeric' }) }) }}</template>
                    </p>
                </template>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirm = null">{{ t('common.cancel') }}</button>
                    <button
                        class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60"
                        :disabled="form.processing || roundBusy"
                        @click="confirm === 'round' ? startRound() : save()"
                    >
                        <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
