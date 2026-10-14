<script setup>
import { computed } from 'vue';
import { useI18n } from '@/i18n';

/*
 * Horizontal bars for comparing categories (one measure, one hue): label on the left, value at the bar end.
 * Every value is written out, so the list doubles as its own table view.
 */
const props = defineProps({
    rows: { type: Array, required: true }, // [{ key, label, value, note? }]
    unit: { type: String, default: '' }, // appended to values, e.g. 'บาท'
    max: { type: Number, default: null }, // shared scale across lists; defaults to the largest value
});

const { formatNumber } = useI18n();
const top = computed(() => props.max ?? Math.max(1, ...props.rows.map((r) => r.value)));
const total = computed(() => props.rows.reduce((n, r) => n + r.value, 0));
const pct = (value) => (total.value ? Math.round((value / total.value) * 100) : 0);
</script>

<template>
    <ul class="space-y-2.5">
        <li v-for="row in rows" :key="row.key" class="group" :title="`${row.label}: ${formatNumber(row.value)}${unit ? ` ${unit}` : ''} (${pct(row.value)}%)`">
            <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span class="min-w-0 truncate text-neutral-700 dark:text-neutral-200">{{ row.label }}</span>
                <span class="shrink-0 font-semibold tabular-nums text-neutral-900 dark:text-white">
                    {{ formatNumber(row.value) }}<span v-if="unit" class="ml-1 text-xs font-normal text-neutral-500 dark:text-neutral-400">{{ unit }}</span>
                    <span class="ml-1.5 text-xs font-normal text-neutral-500 dark:text-neutral-400">{{ pct(row.value) }}%</span>
                </span>
            </div>
            <div class="h-2 rounded-full bg-neutral-100 dark:bg-white/5">
                <div
                    class="h-full rounded-full bg-gold-400 transition-all duration-500 group-hover:bg-gold-500 dark:group-hover:bg-gold-300"
                    :style="{ width: `${Math.max(row.value ? 2 : 0, (row.value / top) * 100)}%` }"
                />
            </div>
            <p v-if="row.note" class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ row.note }}</p>
        </li>
    </ul>
</template>
