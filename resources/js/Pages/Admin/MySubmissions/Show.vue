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
import FileVersions from '@/Components/Admin/FileVersions.vue';
import SubmissionHistory from '@/Components/Admin/SubmissionHistory.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    versions: { type: Array, required: true }, // my file versions and my answers to the reviewers
    files: { type: Object, required: true }, // current PDF, Word, Turnitin and supplementary files
    submission: { type: Object, required: true },
    history: { type: Array, required: true }, // without staff names
    reviewFeedback: { type: Array, required: true }, // "Reviewer 1", "Reviewer 2", ...
    maxMb: { type: Number, required: true },
    journalUrl: { type: String, default: null }, // the journal's submission site (Settings)
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
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

        <DeadlineNotice v-if="submission.status === 'submitted'" :opens-at="opensAt" :deadline="deadline" :phase="phase" />
        <RevisionNotice v-else :status="submission.status" :comment="latestDecision?.comment" :due-date="submission.revisionDueDate" />
        <PublicationAuthorPanel :submission="submission" :max-mb="maxMb" :journal-url="journalUrl" />

        <ReviewerFeedback :rounds="reviewFeedback" />
        <FileVersions v-if="versions.length > 1" :versions="versions" />
        <SubmissionDetail :submission="submission" :files="files" />
        <SubmissionHistory :history="history" />
    </Card>
</template>
