<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import JournalPanel from '@/Components/Admin/JournalPanel.vue';
import SubmissionDetail from '@/Components/Admin/SubmissionDetail.vue';
import FileVersions from '@/Components/Admin/FileVersions.vue';
import SubmissionHistory from '@/Components/Admin/SubmissionHistory.vue';
import { useI18n } from '@/i18n';

// A paper as the journal sees it: details, authors and files, and its decision (no reviews, no internal notes)
defineProps({
    submission: { type: Object, required: true },
    versions: { type: Array, required: true },
    files: { type: Object, required: true }, // current PDF, Word, Turnitin and supplementary files
    journal: { type: Object, required: true }, // JournalController::panel()
    history: { type: Array, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
});

const { t } = useI18n();
</script>

<template>
    <Head :title="submission.code" />

    <Card class="space-y-6">
        <PageHeader icon="newspaper" :title="t('admin.menu.journal')" :subtitle="submission.code" />

        <Link href="/admin/journal" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20">
            <Fa icon="chevron-left" class="text-xs" /> {{ t('submissions.back') }}
        </Link>

        <JournalPanel :submission="submission" :journal="journal" :deadline-passed="phase === 'closed'" />
        <FileVersions v-if="versions.length > 1" :versions="versions" />
        <SubmissionDetail :submission="submission" :files="files" />
        <SubmissionHistory :history="history" />
    </Card>
</template>
