<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import { useI18n } from '@/i18n';

defineProps({
    submissions: { type: Array, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
});

const { t, tr, formatDate } = useI18n();

const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <Head :title="t('admin.menu.my-submissions')" />

    <Card class="space-y-6">
        <PageHeader icon="file-arrow-up" :title="t('admin.menu.my-submissions')" :subtitle="t('submissions.mine.subtitle')" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <DeadlineNotice :deadline="deadline" :is-open="isOpen" class="flex-1" />
            <Link
                v-if="isOpen"
                href="/admin/my-submissions/create"
                class="flex shrink-0 items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300"
            >
                <Fa icon="plus" /> {{ t('submissions.mine.new') }}
            </Link>
        </div>

        <div v-if="!submissions.length" class="flex flex-col items-center rounded-xl border border-dashed border-neutral-300 py-16 text-center dark:border-white/15">
            <Fa icon="file-arrow-up" class="mb-3 text-4xl text-gold-400" />
            <p class="font-medium">{{ t('submissions.mine.empty') }}</p>
            <p class="mt-1 max-w-md text-sm text-neutral-500 dark:text-neutral-400">{{ isOpen ? t('submissions.mine.emptyHint') : t('submissions.mine.emptyClosed') }}</p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.submittedAt') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="item in submissions" :key="item.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                        <td class="px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ item.code }}</td>
                        <td class="max-w-md px-4 py-3">
                            <Link :href="`/admin/my-submissions/${item.id}`" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(item.title) }}</Link>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ tr(item.track.name) }}</td>
                        <td class="px-4 py-3 text-center"><SubmissionStatusBadge :status="item.status" /></td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ formatDate(item.submittedAt) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1.5">
                                <Link :href="`/admin/my-submissions/${item.id}`" :class="iconBtn" :title="t('registrants.view')"><Fa icon="eye" class="text-xs" /></Link>
                                <Link v-if="item.canEdit" :href="`/admin/my-submissions/${item.id}/edit`" :class="iconBtn" :title="t('submissions.edit')"><Fa icon="pen" class="text-xs" /></Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Card>
</template>
