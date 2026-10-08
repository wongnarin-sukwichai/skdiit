<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import ReviewBadge from '@/Components/Admin/ReviewBadge.vue';
import SubmissionDetail from '@/Components/Admin/SubmissionDetail.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    review: { type: Object, required: true },
    submission: { type: Object, required: true },
    recommendations: { type: Array, required: true },
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

const form = useForm({ recommendation: '', comment: '' });
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
                <blockquote class="rounded-lg bg-neutral-50 px-3 py-2 text-sm whitespace-pre-line text-neutral-700 dark:bg-white/5 dark:text-neutral-200">{{ review.comment }}</blockquote>
                <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="lock" class="mr-1" /> {{ t('reviews.form.locked') }}</p>
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
                <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('reviews.form.note') }}</p>
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                    :disabled="!form.recommendation || !form.comment.trim() || form.processing"
                >
                    <Fa icon="paper-plane" /> {{ t('reviews.form.submit') }}
                </button>
            </form>
        </section>

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
