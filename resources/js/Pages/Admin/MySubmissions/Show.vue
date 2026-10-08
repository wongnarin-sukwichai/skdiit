<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import RevisionNotice from '@/Components/Admin/RevisionNotice.vue';
import ReviewerFeedback from '@/Components/Admin/ReviewerFeedback.vue';
import PublicationAuthorPanel from '@/Components/Admin/PublicationAuthorPanel.vue';
import SubmissionDetail from '@/Components/Admin/SubmissionDetail.vue';
import SubmissionHistory from '@/Components/Admin/SubmissionHistory.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    submission: { type: Object, required: true },
    history: { type: Array, required: true }, // without staff names
    reviewFeedback: { type: Array, required: true }, // "Reviewer 1", "Reviewer 2", ...
    maxMb: { type: Number, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
});

const { t } = useI18n();

const revising = computed(() => ['screening_revision', 'revision_minor', 'revision_major'].includes(props.submission.status));
// The editor's latest decision message
const latestDecision = computed(() => props.history.find((e) => ['screened', 'decided'].includes(e.action)));
</script>

<template>
    <Head :title="submission.code" />

    <Card class="space-y-6">
        <PageHeader icon="file-arrow-up" :title="t('admin.menu.my-submissions')" :subtitle="submission.code" />

        <div class="flex flex-wrap items-center gap-3">
            <Link href="/admin/my-submissions" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20">
                <Fa icon="chevron-left" class="text-xs" /> {{ t('submissions.back') }}
            </Link>
            <Link
                v-if="submission.canEdit"
                :href="`/admin/my-submissions/${submission.id}/edit`"
                class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300"
            >
                <Fa icon="pen" /> {{ revising ? t('submissions.revision.action') : t('submissions.edit') }}
            </Link>
        </div>

        <DeadlineNotice v-if="submission.status === 'submitted'" :deadline="deadline" :is-open="isOpen" />
        <RevisionNotice v-else :status="submission.status" :comment="latestDecision?.comment" :due-date="submission.revisionDueDate" />
        <PublicationAuthorPanel :submission="submission" :max-mb="maxMb" />

        <ReviewerFeedback :rounds="reviewFeedback" />
        <SubmissionDetail :submission="submission" />
        <SubmissionHistory :history="history" />
    </Card>
</template>
