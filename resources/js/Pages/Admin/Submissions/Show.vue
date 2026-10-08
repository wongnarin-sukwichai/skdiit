<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import SubmissionDetail from '@/Components/Admin/SubmissionDetail.vue';
import SubmissionHistory from '@/Components/Admin/SubmissionHistory.vue';
import ReviewersPanel from '@/Components/Admin/ReviewersPanel.vue';
import DecisionPanel from '@/Components/Admin/DecisionPanel.vue';
import PublicationStaffPanel from '@/Components/Admin/PublicationStaffPanel.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    submission: { type: Object, required: true },
    tracks: { type: Array, required: true },
    conflict: { type: Boolean, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    canScreen: { type: Boolean, required: true },
    history: { type: Array, required: true },
    reviewRound: { type: Number, required: true },
    reviews: { type: Array, required: true },
    reviewerOptions: { type: Array, required: true },
    canAssign: { type: Boolean, required: true },
    canDecide: { type: Boolean, required: true },
    canStartRound: { type: Boolean, required: true },
    canSchedule: { type: Boolean, required: true },
});

const { t, tr, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

// Editors decide which track the paper belongs to
const trackForm = useForm({ track_id: props.submission.track.id });
watch(() => props.submission.track.id, (id) => {
    trackForm.defaults({ track_id: id });
    trackForm.reset();
});
function saveTrack() {
    trackForm.put(`/admin/submissions/${props.submission.id}/track`, { preserveScroll: true, preserveState: true });
}

// Screening decision
const decisions = [
    { value: 'pass', icon: 'circle-check', active: 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
    { value: 'revise', icon: 'pen', active: 'border-orange-500 bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300' },
    { value: 'reject', icon: 'xmark', active: 'border-red-500 bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300' },
];
const screenForm = useForm({ decision: '', comment: '' });
const confirmOpen = ref(false);
function screen() {
    screenForm.post(`/admin/submissions/${props.submission.id}/screening`, {
        preserveScroll: true,
        onSuccess: () => screenForm.reset(),
        onFinish: () => (confirmOpen.value = false),
    });
}

const box = 'rounded-xl border border-neutral-200 p-4 dark:border-white/10';
</script>

<template>
    <Head :title="submission.code" />

    <Card class="space-y-6">
        <PageHeader icon="file-lines" :title="t('admin.menu.submissions')" :subtitle="submission.code" />

        <Link href="/admin/submissions" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20">
            <Fa icon="chevron-left" class="text-xs" /> {{ t('submissions.back') }}
        </Link>

        <!-- Possible conflict of interest: warn only, the editor decides -->
        <div v-if="conflict" class="flex items-start gap-3 rounded-xl border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-400/30 dark:bg-orange-400/10 dark:text-orange-200">
            <Fa icon="triangle-exclamation" class="mt-0.5" />
            <p>{{ t('submissions.manage.conflict') }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Track -->
            <section :class="box">
                <h2 class="mb-3 flex items-center gap-2 font-semibold"><Fa icon="layer-group" class="text-gold-400" /> {{ t('submissions.fields.track') }}</h2>
                <form class="flex flex-wrap gap-2" @submit.prevent="saveTrack">
                    <select v-model="trackForm.track_id" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none focus:border-gold-400 dark:border-white/10 dark:bg-ink-800">
                        <option v-for="track in tracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                    </select>
                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                        :disabled="!trackForm.isDirty || trackForm.processing"
                    >
                        <Fa icon="floppy-disk" /> {{ t('submissions.manage.moveTrack') }}
                    </button>
                </form>
            </section>

            <!-- Screening opens after the deadline, once authors can no longer change their files -->
            <section :class="box">
                <h2 class="mb-3 flex items-center gap-2 font-semibold"><Fa icon="clipboard-check" class="text-gold-400" /> {{ t('submissions.manage.screening') }}</h2>

                <form v-if="canScreen" class="space-y-3" @submit.prevent="confirmOpen = true">
                    <div class="grid gap-2 sm:grid-cols-3" role="radiogroup">
                        <button
                            v-for="option in decisions"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="screenForm.decision === option.value"
                            :class="screenForm.decision === option.value ? option.active : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                            class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                            @click="screenForm.decision = option.value"
                        >
                            <Fa :icon="option.icon" /> {{ t(`submissions.screening.${option.value}`) }}
                        </button>
                    </div>
                    <span v-if="screenForm.errors.decision" class="block text-xs text-red-500 dark:text-red-400">{{ t(screenForm.errors.decision) }}</span>
                    <label class="block">
                        <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.screening.comment') }} ({{ t('submissions.form.optional') }})</span>
                        <textarea
                            v-model="screenForm.comment"
                            rows="4"
                            maxlength="5000"
                            :placeholder="t(`submissions.screening.placeholder.${screenForm.decision || 'none'}`)"
                            class="w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800"
                        />
                    </label>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('submissions.screening.note') }}</p>
                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                        :disabled="!screenForm.decision || screenForm.processing"
                    >
                        <Fa icon="floppy-disk" /> {{ t('submissions.screening.save') }}
                    </button>
                </form>

                <p v-else-if="submission.status === 'submitted'" class="text-sm text-neutral-500 dark:text-neutral-400">
                    <Fa icon="lock" class="mr-1" />
                    {{ deadline ? t('submissions.manage.screeningWaits', { date: when(deadline) }) : t('submissions.manage.screeningNoDeadline') }}
                </p>
                <p v-else class="text-sm text-neutral-500 dark:text-neutral-400">
                    <Fa icon="circle-check" class="mr-1 text-gold-500" /> {{ t(`submissions.screening.done.${['screening_revision', 'screening_rejected'].includes(submission.status) ? submission.status : 'passed'}`) }}
                </p>
            </section>
        </div>

        <DecisionPanel :submission="submission" :can-decide="canDecide" :can-start-round="canStartRound" />
        <PublicationStaffPanel :submission="submission" :can-schedule="canSchedule" />
        <!-- Outcome so far, for staff -->
        <div v-if="submission.revisionDueDate" class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
            <Fa icon="calendar-days" /> {{ t('decision.authorDue', { date: formatDate(submission.revisionDueDate, { day: 'numeric', month: 'long', year: 'numeric' }) }) }}
        </div>
        <ReviewersPanel
            v-if="reviewRound > 0"
            :submission-id="submission.id"
            :round="reviewRound"
            :reviews="reviews"
            :options="reviewerOptions"
            :can-assign="canAssign"
        />
        <SubmissionDetail :submission="submission" />
        <SubmissionHistory :history="history" />

        <!-- One editor's decision is final for this round, so confirm first -->
        <Modal :show="confirmOpen" @close="confirmOpen = false">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="clipboard-check" />
                </span>
                <h2 class="text-xl font-semibold">{{ t('submissions.screening.confirmTitle') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ t('submissions.screening.confirmText', { code: submission.code, decision: screenForm.decision ? t(`submissions.screening.${screenForm.decision}`) : '' }) }}
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirmOpen = false">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="screenForm.processing" @click="screen">
                        <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                    </button>
                </div>
            </div>
        </Modal>
    </Card>
</template>
