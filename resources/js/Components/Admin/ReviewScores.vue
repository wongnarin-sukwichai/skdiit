<script setup>
import { useI18n } from '@/i18n';

// One evaluation's scores (1-5 per criterion) with their average, read only
defineProps({
    scores: { type: Array, required: true }, // [{ criterion, name: { en, th }, score }]
    average: { type: Number, default: null },
});

const { t, tr } = useI18n();
</script>

<template>
    <div class="space-y-1.5">
        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">
            {{ t('reviews.form.scores') }}
            <span v-if="average !== null" class="ml-1 text-gold-700 dark:text-gold-300">· {{ t('reviews.scores.average') }} {{ average }}/5</span>
        </p>
        <ul class="space-y-1">
            <li v-for="item in scores" :key="item.criterion" class="flex items-center gap-3 text-sm">
                <span class="min-w-0 flex-1 truncate">{{ tr(item.name) }}</span>
                <span class="flex gap-0.5" :aria-label="`${item.score}/5`">
                    <span v-for="n in 5" :key="n" :class="n <= item.score ? 'bg-gold-400' : 'bg-neutral-200 dark:bg-white/10'" class="h-2 w-5 rounded-full" />
                </span>
                <span class="w-6 text-right font-semibold tabular-nums">{{ item.score }}</span>
            </li>
        </ul>
    </div>
</template>
