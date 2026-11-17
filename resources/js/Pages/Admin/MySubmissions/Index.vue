<script setup>
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import SubmissionWindowAlert from '@/Components/Admin/SubmissionWindowAlert.vue';
import AuthorJourneyMini from '@/Components/Admin/Journey/AuthorJourneyMini.vue';
import AuthorPapersBoard from '@/Components/Admin/Journey/AuthorPapersBoard.vue';
import AuthorNextStep from '@/Components/Admin/AuthorNextStep.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    submissions: { type: Array, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
});

const { t, tr, formatDate } = useI18n();

// Outside the submission period the button stays, but shows an alert instead of the form
// (also after the server sent the author back here, see MySubmissionController::outsideWindow)
const windowAlert = ref(!props.isOpen && !!usePage().props.flash?.submissionWindow);

const newBtn = 'flex shrink-0 items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300';
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <Head :title="t('admin.menu.my-submissions')" />

    <Card class="space-y-6">
        <PageHeader icon="file-arrow-up" :title="t('admin.menu.my-submissions')" :subtitle="t('submissions.mine.subtitle')" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <DeadlineNotice :opens-at="opensAt" :deadline="deadline" :phase="phase" class="flex-1" />
            <Link v-if="isOpen" :href="$url('/admin/my-submissions/create')" :class="newBtn">
                <Fa icon="plus" /> {{ t('submissions.mine.new') }}
            </Link>
            <button v-else type="button" :class="[newBtn, 'opacity-60']" @click="windowAlert = true">
                <Fa icon="plus" /> {{ t('submissions.mine.new') }}
            </button>
        </div>

        <!-- Every paper on one path: each sits under the step it is on -->
        <section v-if="submissions.length" class="rounded-xl border border-neutral-200 p-4 sm:p-5 dark:border-white/10">
            <h2 class="mb-4 flex items-center gap-2 font-semibold"><Fa icon="chart-line" class="text-gold-500" /> {{ t('admin.dash.timeline') }}</h2>
            <AuthorPapersBoard :papers="submissions" />
        </section>

        <div v-if="!submissions.length" class="flex flex-col items-center rounded-xl border border-dashed border-neutral-300 py-16 text-center dark:border-white/15">
            <Fa icon="file-arrow-up" class="mb-3 text-4xl text-gold-400" />
            <p class="font-medium">{{ t('submissions.mine.empty') }}</p>
            <p class="mt-1 max-w-md text-sm text-neutral-500 dark:text-neutral-400">{{ t(isOpen ? 'submissions.mine.emptyHint' : phase === 'upcoming' ? 'submissions.mine.emptyUpcoming' : 'submissions.mine.emptyClosed') }}</p>
        </div>

        <div v-else class="rounded-xl border border-neutral-200 dark:border-white/10">
            <h2 class="flex items-center gap-2 border-b border-neutral-200 px-4 py-3 font-semibold dark:border-white/10"><Fa icon="file-lines" class="text-gold-500" /> {{ t('admin.dash.myPapers') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-225 text-sm">
                    <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('submissions.journey.column') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('submissions.next.column') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('submissions.submittedAt') }}</th>
                            <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="item in submissions" :key="item.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                            <!-- Blue bar on the left: the author's move -->
                            <td :class="item.next.mine ? 'border-sky-400' : 'border-transparent'" class="border-l-4 px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ item.code }}</td>
                            <td class="max-w-md px-4 py-3">
                                <Link :href="$url(`/admin/my-submissions/${item.id}`)" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(item.title) }}</Link>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ item.track ? tr(item.track.name) : t('submissions.noTrack') }}</td>
                            <td class="px-4 py-3"><AuthorJourneyMini :status="item.status" :editable="item.canEdit && item.status === 'submitted'" :final-approved="!!item.cameraReadyApproved" /></td>
                            <td class="px-4 py-3"><AuthorNextStep :next="item.next" /></td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ formatDate(item.submittedAt) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1.5">
                                    <Link :href="$url(`/admin/my-submissions/${item.id}`)" :class="iconBtn" :title="t('registrants.view')"><Fa icon="eye" class="text-xs" /></Link>
                                    <Link v-if="item.canEdit" :href="$url(`/admin/my-submissions/${item.id}/edit`)" :class="iconBtn" :title="t('submissions.edit')"><Fa icon="pen" class="text-xs" /></Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Card>

    <SubmissionWindowAlert v-if="!isOpen" :show="windowAlert" :phase="phase" :opens-at="opensAt" :deadline="deadline" @close="windowAlert = false" />
</template>
