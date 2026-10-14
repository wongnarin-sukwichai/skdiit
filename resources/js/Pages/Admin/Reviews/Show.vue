<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import ReviewBadge from '@/Components/Admin/ReviewBadge.vue';
import SubmissionDetail from '@/Components/Admin/SubmissionDetail.vue';
import FileVersions from '@/Components/Admin/FileVersions.vue';
import { useI18n } from '@/i18n';
import { formatFileSize } from '@/utils/format';

const props = defineProps({
    versions: { type: Array, required: true }, // file versions; reviewers are numbered, never named
    review: { type: Object, required: true },
    submission: { type: Object, required: true },
    recommendations: { type: Array, required: true },
    maxMb: { type: Number, required: true }, // upload limit (Settings)
});

const { t, formatDate } = useI18n();
const day = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric' });
const when = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const icons = { accept: 'circle-check', minor: 'pen', major: 'pen-to-square', reject: 'xmark' };
const active = {
    accept: 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    minor: 'border-gold-500 bg-gold-50 text-gold-700 dark:bg-gold-500/15 dark:text-gold-300',
    major: 'border-orange-500 bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
    reject: 'border-red-500 bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

// Starts from the saved draft, if any
const form = useForm({
    recommendation: props.review.recommendation ?? '',
    comment: props.review.comment ?? '',
    comment_to_editor: props.review.commentToEditor ?? '',
    no_conflict: false,
    attachment: null,
});

// Save without sending (the editors do not see a draft)
const savingDraft = ref(false);
function saveDraft() {
    router.post(`/admin/reviews/${props.review.id}/draft`, {
        recommendation: form.recommendation || null,
        comment: form.comment,
        comment_to_editor: form.comment_to_editor,
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (savingDraft.value = true),
        onFinish: () => (savingDraft.value = false),
    });
}

// "I cannot review this": tells the editors, who assign someone else
const unavailableOpen = ref(false);
const unavailableForm = useForm({ reason: '' });
function sendUnavailable() {
    unavailableForm.post(`/admin/reviews/${props.review.id}/unavailable`, { preserveScroll: true, onSuccess: () => (unavailableOpen.value = false) });
}
const picker = ref(null);
function pickAttachment(event) {
    form.attachment = event.target.files[0] ?? null;
}
const confirmOpen = ref(false);
function submit() {
    form.post(`/admin/reviews/${props.review.id}`, {
        preserveScroll: true,
        onFinish: () => (confirmOpen.value = false),
    });
}

const box = 'rounded-xl border border-neutral-200 p-4 dark:border-white/10';
</script>

<template>
    <Head :title="submission.code" />

    <Card class="space-y-6">
        <PageHeader icon="clipboard-check" :title="t('admin.menu.reviews')" :subtitle="submission.code" />

        <div class="flex flex-wrap items-center gap-3">
            <Link href="/admin/reviews" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20">
                <Fa icon="chevron-left" class="text-xs" /> {{ t('submissions.back') }}
            </Link>
            <ReviewBadge kind="state" :value="review.state" />
            <span class="text-sm" :class="review.state === 'overdue' ? 'font-medium text-red-600 dark:text-red-400' : 'text-neutral-500 dark:text-neutral-400'">
                <Fa icon="calendar-days" class="mr-1" /> {{ t('reviews.due') }} {{ day(review.dueDate) }}
            </span>
        </div>

        <!-- The paper (authors are visible to reviewers) -->
        <FileVersions v-if="versions.length > 1" :versions="versions" />
        <SubmissionDetail :submission="submission" />

        <!-- Evaluation -->
        <section :class="box">
            <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold"><Fa icon="clipboard-check" class="text-gold-400" /> {{ t('reviews.form.title') }}</h2>

            <!-- Already sent: read only -->
            <div v-if="review.state === 'submitted'" class="space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <ReviewBadge kind="recommendation" :value="review.recommendation" />
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('reviews.submittedOn') }} {{ when(review.submittedAt) }}</span>
                </div>
                <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">{{ t('reviews.form.comment') }}</p>
                <blockquote class="rounded-lg bg-neutral-50 px-3 py-2 text-sm whitespace-pre-line text-neutral-700 dark:bg-white/5 dark:text-neutral-200">{{ review.comment }}</blockquote>
                <template v-if="review.commentToEditor">
                    <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400"><Fa icon="lock" class="mr-1" />{{ t('reviews.form.toEditor') }}</p>
                    <blockquote class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm whitespace-pre-line text-neutral-700 dark:border-amber-400/20 dark:bg-amber-400/5 dark:text-neutral-200">{{ review.commentToEditor }}</blockquote>
                </template>
                <a v-if="review.attachment" :href="review.attachment.url" class="inline-flex items-center gap-2 text-sm text-gold-700 hover:underline dark:text-gold-400">
                    <Fa icon="paperclip" /> {{ review.attachment.name }} · {{ formatFileSize(review.attachment.size) }}
                </a>
                <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="lock" class="mr-1" /> {{ t('reviews.form.locked') }}</p>
            </div>

            <!-- The reviewer said they cannot review: waiting for the editors to replace them -->
            <div v-else-if="review.state === 'unavailable'" class="rounded-lg border border-neutral-300 bg-neutral-50 p-4 text-sm dark:border-white/15 dark:bg-white/5">
                <p class="font-medium"><Fa icon="user-xmark" class="mr-1.5 text-neutral-500" />{{ t('reviews.unavailable.done') }}</p>
                <p class="mt-1 text-neutral-600 dark:text-neutral-300">{{ t('reviews.unavailable.yourReason') }}: {{ review.unavailable.reason }}</p>
            </div>

            <!-- The editor decided before this review came in -->
            <p v-else-if="review.state === 'closed'" class="text-sm text-neutral-500 dark:text-neutral-400">
                <Fa icon="lock" class="mr-1" /> {{ t('reviews.form.closed') }}
            </p>

            <form v-else class="space-y-4" @submit.prevent="confirmOpen = true">
                <div>
                    <p class="mb-2 text-sm font-medium">{{ t('reviews.form.recommendation') }} <span class="text-red-500">*</span></p>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4" role="radiogroup">
                        <button
                            v-for="value in recommendations"
                            :key="value"
                            type="button"
                            role="radio"
                            :aria-checked="form.recommendation === value"
                            :class="form.recommendation === value ? active[value] : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                            class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                            @click="form.recommendation = value"
                        >
                            <Fa :icon="icons[value]" /> {{ t(`reviews.recommendation.${value}`) }}
                        </button>
                    </div>
                    <span v-if="form.errors.recommendation" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.recommendation) }}</span>
                </div>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium">{{ t('reviews.form.comment') }} <span class="text-red-500">*</span></span>
                    <textarea
                        v-model="form.comment"
                        rows="10"
                        maxlength="20000"
                        :placeholder="t('reviews.form.commentPlaceholder')"
                        :class="form.errors.comment ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10'"
                        class="w-full rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800"
                    />
                    <span v-if="form.errors.comment" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.comment) }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium"><Fa icon="lock" class="mr-1 text-amber-500" />{{ t('reviews.form.toEditor') }}</span>
                    <textarea
                        v-model="form.comment_to_editor"
                        rows="3"
                        maxlength="20000"
                        :placeholder="t('reviews.form.toEditorPlaceholder')"
                        class="w-full rounded-lg border border-amber-200 bg-amber-50/50 px-3 py-2.5 text-sm outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-amber-400/20 dark:bg-amber-400/5"
                    />
                    <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('reviews.form.toEditorHint') }}</span>
                </label>
                <!-- Optional file: e.g. the paper with comments on it -->
                <div>
                    <p class="mb-1.5 text-sm font-medium">{{ t('reviews.form.attachment') }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm transition hover:border-gold-400 dark:border-white/15" @click="picker.click()">
                            <Fa icon="paperclip" /> {{ form.attachment ? t('reviews.form.changeFile') : t('reviews.form.chooseFile') }}
                        </button>
                        <span v-if="form.attachment" class="flex items-center gap-2 text-sm">
                            {{ form.attachment.name }} · {{ formatFileSize(form.attachment.size) }}
                            <button type="button" class="text-neutral-400 hover:text-red-500" :aria-label="t('common.cancel')" @click="form.attachment = null; picker.value = ''"><Fa icon="xmark" /></button>
                        </span>
                        <input ref="picker" type="file" accept=".pdf,.doc,.docx" class="hidden" @change="pickAttachment">
                    </div>
                    <span v-if="form.errors.attachment" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.attachment, { max: props.maxMb }) }}</span>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ t('reviews.form.attachmentHint') }}</p>
                </div>

                <!-- Declared with every evaluation -->
                <label class="flex items-start gap-3 rounded-lg border p-3 text-sm" :class="form.errors.no_conflict ? 'border-red-400' : 'border-neutral-200 dark:border-white/10'">
                    <input v-model="form.no_conflict" type="checkbox" class="mt-0.5 size-4 accent-gold-500">
                    <span>{{ t('reviews.form.conflictDeclare') }} <span class="text-red-500">*</span>
                        <span v-if="form.errors.no_conflict" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.no_conflict) }}</span>
                    </span>
                </label>

                <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('reviews.form.note') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                        :disabled="!form.recommendation || !form.comment.trim() || !form.no_conflict || form.processing"
                    >
                        <Fa icon="paper-plane" /> {{ t('reviews.form.submit') }}
                    </button>
                    <button type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 disabled:opacity-50 dark:border-white/15" :disabled="savingDraft" @click="saveDraft">
                        <Fa icon="floppy-disk" /> {{ t('reviews.form.saveDraft') }}
                    </button>
                    <span v-if="review.draftSavedAt" class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('reviews.form.draftAt', { date: when(review.draftSavedAt) }) }}</span>
                    <button type="button" class="ml-auto flex items-center gap-2 text-sm text-neutral-500 transition hover:text-red-600 dark:text-neutral-400 dark:hover:text-red-400" @click="unavailableOpen = true">
                        <Fa icon="user-xmark" /> {{ t('reviews.unavailable.button') }}
                    </button>
                </div>
            </form>
        </section>

        <Modal :show="unavailableOpen" @close="unavailableOpen = false">
            <form class="p-6 sm:p-8" @submit.prevent="sendUnavailable">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-red-500/15 text-lg text-red-400"><Fa icon="user-xmark" /></span>
                <h2 class="text-xl font-semibold">{{ t('reviews.unavailable.title') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ t('reviews.unavailable.text') }}</p>
                <textarea
                    v-model="unavailableForm.reason"
                    rows="3"
                    :placeholder="t('reviews.unavailable.placeholder')"
                    :class="unavailableForm.errors.reason ? 'border-red-500/70' : 'border-white/10'"
                    class="mt-4 w-full rounded-lg border bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400"
                />
                <span v-if="unavailableForm.errors.reason" class="mt-1 block text-xs text-red-400">{{ t(unavailableForm.errors.reason) }}</span>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="unavailableOpen = false">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400 disabled:opacity-60" :disabled="unavailableForm.processing || !unavailableForm.reason.trim()">
                        <Fa icon="paper-plane" /> {{ t('reviews.unavailable.send') }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="confirmOpen" @close="confirmOpen = false">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="paper-plane" />
                </span>
                <h2 class="text-xl font-semibold">{{ t('reviews.form.confirmTitle') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ t('reviews.form.confirmText', { code: submission.code, recommendation: form.recommendation ? t(`reviews.recommendation.${form.recommendation}`) : '' }) }}
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirmOpen = false">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing" @click="submit">
                        <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                    </button>
                </div>
            </div>
        </Modal>
    </Card>
</template>
