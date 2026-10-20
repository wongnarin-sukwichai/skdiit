<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import AuthorJourney from '@/Components/Admin/Journey/AuthorJourney.vue';
import ReviewerFeedback from '@/Components/Admin/ReviewerFeedback.vue';
import PublicationAuthorPanel from '@/Components/Admin/PublicationAuthorPanel.vue';
import SubmissionDetail from '@/Components/Admin/SubmissionDetail.vue';
import FileVersions from '@/Components/Admin/FileVersions.vue';
import SubmissionHistory from '@/Components/Admin/SubmissionHistory.vue';
import { useI18n } from '@/i18n';
import { useConferenceDates } from '@/composables/useConferenceDates';

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

const dates = useConferenceDates();
// The editor's latest decision message
const latestDecision = computed(() => props.history.find((e) => ['screened', 'decided'].includes(e.action)));
// A revision sent before keeps the revision step on the path
const hadRevision = computed(() => props.history.some((e) => e.action === 'resubmitted'));

</script>

<template>
    <Head :title="submission.code" />

    <Card class="space-y-6">
        <PageHeader icon="file-arrow-up" :title="t('admin.menu.my-submissions')" :subtitle="submission.code" />

        <Link href="/admin/my-submissions" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20">
            <Fa icon="chevron-left" class="text-xs" /> {{ t('submissions.back') }}
        </Link>

        <!-- Where the paper is and whose turn it is; the card holds the edit / revise button -->
        <AuthorJourney
            :submission="submission"
            :window="{ deadline, phase }"
            :dates="dates"
            :had-revision="hadRevision"
            :message="latestDecision?.comment"
            :links="{ edit: submission.canEdit ? `/admin/my-submissions/${submission.id}/edit` : null, journal: journalUrl }"
        />
        <div id="publication" class="scroll-mt-24">
            <PublicationAuthorPanel :submission="submission" :max-mb="maxMb" :journal-url="journalUrl" />
        </div>

        <ReviewerFeedback :rounds="reviewFeedback" />
        <FileVersions v-if="versions.length > 1" :versions="versions" />
        <SubmissionDetail :submission="submission" :files="files" />
        <SubmissionHistory :history="history" />
    </Card>
</template>
