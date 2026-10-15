<script setup>
import { useI18n } from '@/i18n';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import ImpersonateButton from '@/Components/Admin/ImpersonateButton.vue';
import { formatFileSize } from '@/utils/format';

defineProps({
    submission: { type: Object, required: true }, // Submission::toClient(full: true)
    contactPhone: { type: String, default: null }, // staff only
    files: { type: Object, default: null }, // Submission::currentFiles(): pdf, word, turnitin, supplementary (not for reviewers)
});

const { t, tr, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const label = 'mb-1 text-xs font-semibold tracking-wide text-neutral-500 uppercase dark:text-neutral-400';
const box = 'rounded-xl border border-neutral-200 p-4 dark:border-white/10';
</script>

<template>
    <div class="space-y-6">
        <!-- Summary -->
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <span class="font-mono font-semibold text-gold-600 dark:text-gold-400">{{ submission.code }}</span>
            <SubmissionStatusBadge :status="submission.status" />
            <span class="text-neutral-500 dark:text-neutral-400"><Fa icon="layer-group" class="mr-1" /> {{ tr(submission.track.name) }}</span>
            <span class="text-neutral-500 dark:text-neutral-400"><Fa icon="clock" class="mr-1" /> {{ t('submissions.submittedAt') }} {{ when(submission.submittedAt) }}</span>
            <span v-if="submission.updatedAt !== submission.submittedAt" class="text-neutral-500 dark:text-neutral-400">
                <Fa icon="pen" class="mr-1" /> {{ t('submissions.updatedAt') }} {{ when(submission.updatedAt) }}
            </span>
        </div>

        <!-- Titles, abstracts and keywords in both languages -->
        <div class="grid gap-4 lg:grid-cols-2">
            <section v-for="lang in ['th', 'en']" :key="lang" :class="box" class="space-y-4">
                <p class="text-xs font-semibold text-gold-600 dark:text-gold-400">{{ t(`lang.${lang}`) }}</p>
                <div>
                    <p :class="label">{{ t('submissions.fields.title') }}</p>
                    <p class="text-lg font-semibold">{{ submission.title[lang] }}</p>
                </div>
                <div>
                    <p :class="label">{{ t('submissions.fields.abstract') }}</p>
                    <p class="text-sm leading-relaxed whitespace-pre-line text-neutral-700 dark:text-neutral-300">{{ submission.abstract[lang] }}</p>
                </div>
                <div>
                    <p :class="label">{{ t('submissions.fields.keywords') }}</p>
                    <p class="text-sm text-neutral-700 dark:text-neutral-300">{{ submission.keywords[lang] }}</p>
                </div>
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
            <!-- Authors in byline order -->
            <section :class="box">
                <p :class="label" class="mb-3">{{ t('submissions.fields.authors') }}</p>
                <ol class="space-y-3">
                    <li v-for="(author, i) in submission.authors" :key="i" class="flex gap-3 text-sm">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-gold-100 text-xs font-semibold text-gold-700 dark:bg-gold-400/15 dark:text-gold-300">{{ i + 1 }}</span>
                        <span>
                            <span class="block font-medium">{{ author.name }}</span>
                            <span class="block text-neutral-500 dark:text-neutral-400">{{ author.affiliation }}<template v-if="author.email"> · {{ author.email }}</template></span>
                        </span>
                    </li>
                </ol>
            </section>

            <div class="space-y-4">
                <!-- The submitting account is the paper's only contact -->
                <section :class="box">
                    <p :class="label">{{ t('submissions.contact') }}</p>
                    <p class="text-sm font-medium">{{ submission.submitter.name }}</p>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ submission.submitter.email }}</p>
                    <a v-if="contactPhone" :href="`tel:${contactPhone}`" class="text-sm text-gold-700 hover:underline dark:text-gold-400"><Fa icon="phone" class="mr-1" />{{ contactPhone }}</a>
                    <ImpersonateButton v-if="submission.submitter.id" :user="submission.submitter" class="mt-2" />
                </section>
                <section v-if="files" :class="box" class="space-y-2">
                    <p :class="label">{{ t('submissions.files.title') }}</p>
                    <template v-for="kind in ['pdf', 'word', 'turnitin']" :key="kind">
                        <a v-if="files[kind]" :href="files[kind].url" class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 text-sm transition hover:border-gold-400 dark:border-white/10">
                            <Fa :icon="{ pdf: 'file-pdf', word: 'file-word', turnitin: 'file-circle-check' }[kind]" class="text-2xl text-gold-500" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs text-neutral-500 dark:text-neutral-400">{{ t(`submissions.files.${kind}`) }}</span>
                                <span class="block truncate font-medium">{{ files[kind].name }}</span>
                            </span>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatFileSize(files[kind].size) }}</span>
                            <Fa icon="download" class="text-neutral-400" />
                        </a>
                        <p v-else class="text-sm text-neutral-400"><Fa icon="circle-exclamation" class="mr-1" />{{ t(`submissions.files.${kind}`) }}: {{ t('submissions.files.missing') }}</p>
                    </template>
                    <div v-if="files.supplementary.length">
                        <p class="mt-3 mb-1 text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.files.supplementary') }}</p>
                        <a v-for="file in files.supplementary" :key="file.id" :href="file.url" class="flex items-center gap-2 py-1 text-sm hover:text-gold-600 dark:hover:text-gold-400">
                            <Fa icon="paperclip" class="text-neutral-400" /> <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatFileSize(file.size) }}</span>
                        </a>
                    </div>
                </section>
                <section v-else :class="box">
                    <p :class="label">{{ t('submissions.fields.file') }}</p>
                    <a :href="submission.file.url" class="mt-1 flex items-center gap-3 rounded-lg border border-neutral-200 p-3 text-sm transition hover:border-gold-400 dark:border-white/10">
                        <Fa :icon="submission.file.name.toLowerCase().endsWith('.pdf') ? 'file-pdf' : 'file-word'" class="text-2xl text-gold-500" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ submission.file.name }}</span>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatFileSize(submission.file.size) }}</span>
                        </span>
                        <Fa icon="download" class="text-neutral-400" />
                    </a>
                </section>
            </div>
        </div>
    </div>
</template>
