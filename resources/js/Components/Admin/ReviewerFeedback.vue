<script setup>
import ReviewBadge from '@/Components/Admin/ReviewBadge.vue';
import { useI18n } from '@/i18n';

// Reviewer comments for the author: numbered per round, never named (Submission::reviewsForAuthor)
defineProps({
    rounds: { type: Array, required: true }, // [{ round, reviews: [{ number, recommendation, comment }] }], newest first
});

const { t } = useI18n();
</script>

<template>
    <section v-if="rounds.length" class="rounded-xl border border-neutral-200 p-4 dark:border-white/10">
        <h2 class="mb-4 flex items-center gap-2 font-semibold"><Fa icon="comment" class="text-gold-400" /> {{ t('reviews.feedback.title') }}</h2>
        <div class="space-y-5">
            <div v-for="group in rounds" :key="group.round">
                <p v-if="rounds.length > 1" class="mb-2 text-xs font-semibold tracking-wide text-neutral-500 uppercase dark:text-neutral-400">{{ t('reviews.round', { round: group.round }) }}</p>
                <ul class="space-y-3">
                    <li v-for="review in group.reviews" :key="review.number" class="rounded-lg bg-neutral-50 p-3 dark:bg-white/5">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ t('reviews.feedback.reviewer', { n: review.number }) }}</span>
                            <ReviewBadge kind="recommendation" :value="review.recommendation" />
                        </div>
                        <p v-if="review.comment" class="text-sm whitespace-pre-line text-neutral-700 dark:text-neutral-200">{{ review.comment }}</p>
                        <p v-else-if="!review.file" class="text-sm text-neutral-400 italic">{{ t('versions.noComment') }}</p>
                        <a v-if="review.file" :href="$url(review.file.url)" class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-2.5 py-1 text-xs transition hover:border-gold-400 dark:border-white/15 dark:bg-ink-900"><Fa icon="paperclip" class="text-gold-500" /> {{ review.file.name }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
