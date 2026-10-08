<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import ReviewBadge from '@/Components/Admin/ReviewBadge.vue';
import { useI18n } from '@/i18n';

defineProps({
    reviews: { type: Array, required: true }, // open work first, then by due date
});

const { t, tr, formatDate } = useI18n();
const day = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head :title="t('admin.menu.reviews')" />

    <Card class="space-y-6">
        <PageHeader icon="clipboard-check" :title="t('admin.menu.reviews')" :subtitle="t('reviews.mine.subtitle')" />

        <div v-if="!reviews.length" class="flex flex-col items-center rounded-xl border border-dashed border-neutral-300 py-16 text-center dark:border-white/15">
            <Fa icon="clipboard-check" class="mb-3 text-4xl text-gold-400" />
            <p class="font-medium">{{ t('reviews.mine.empty') }}</p>
            <p class="mt-1 max-w-md text-sm text-neutral-500 dark:text-neutral-400">{{ t('reviews.mine.emptyHint') }}</p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.title') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('submissions.fields.track') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('reviews.due') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="review in reviews" :key="review.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                        <td class="px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ review.paper.code }}</td>
                        <td class="max-w-md px-4 py-3">
                            <Link :href="`/admin/reviews/${review.id}`" class="line-clamp-2 font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(review.paper.title) }}</Link>
                            <span v-if="review.round > 1" class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('reviews.round', { round: review.round }) }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ tr(review.paper.track.name) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap" :class="review.state === 'overdue' ? 'font-medium text-red-600 dark:text-red-400' : 'text-neutral-500 dark:text-neutral-400'">{{ day(review.dueDate) }}</td>
                        <td class="px-4 py-3 text-center"><ReviewBadge kind="state" :value="review.state" /></td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/admin/reviews/${review.id}`"
                                :class="review.state === 'submitted'
                                    ? 'border border-neutral-300 hover:border-gold-400 dark:border-white/20'
                                    : 'bg-gold-400 font-semibold text-black hover:bg-gold-300'"
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs whitespace-nowrap transition"
                            >
                                <Fa :icon="review.state === 'submitted' ? 'eye' : 'pen'" />
                                {{ review.state === 'submitted' ? t('registrants.view') : t('reviews.mine.evaluate') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Card>
</template>
