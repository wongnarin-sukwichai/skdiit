<script setup>
import { computed, ref } from 'vue';
import { useI18n } from '@/i18n';

/*
 * One series over time (line + soft area, single hue). Hover anywhere shows a crosshair and the day's value.
 * The latest point is labelled; a total sits in the corner, so the chart reads without hovering.
 */
const props = defineProps({
    points: { type: Array, required: true }, // [{ date: 'YYYY-MM-DD', value }]
    unit: { type: String, default: '' },
    label: { type: String, default: '' }, // what one point counts, for the tooltip
});

const { formatDate, formatNumber } = useI18n();

const W = 640;
const H = 200;
const pad = { top: 16, right: 16, bottom: 26, left: 36 };
const max = computed(() => Math.max(1, ...props.points.map((p) => p.value)));
// A round top for the axis: 1, 2, 5 × 10^n
const niceMax = computed(() => {
    const step = 10 ** Math.floor(Math.log10(max.value));
    return [1, 2, 5, 10].map((m) => m * step).find((v) => v >= max.value);
});
const x = (i) => pad.left + (i / Math.max(1, props.points.length - 1)) * (W - pad.left - pad.right);
const y = (v) => pad.top + (1 - v / niceMax.value) * (H - pad.top - pad.bottom);

const line = computed(() => props.points.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' '));
const area = computed(() => `${line.value} L${x(props.points.length - 1)},${y(0)} L${x(0)},${y(0)} Z`);
const ticks = computed(() => [0, 0.5, 1].map((f) => Math.round(niceMax.value * f)));
const dateTicks = computed(() => {
    const n = props.points.length;
    return [0, Math.floor((n - 1) / 2), n - 1].map((i) => ({ i, label: formatDate(props.points[i].date, { day: 'numeric', month: 'short' }) }));
});

// Hover: nearest point to the pointer
const svg = ref(null);
const hover = ref(null);
function move(event) {
    const box = svg.value.getBoundingClientRect();
    const px = ((event.clientX - box.left) / box.width) * W;
    const i = Math.round(((px - pad.left) / (W - pad.left - pad.right)) * (props.points.length - 1));
    hover.value = Math.min(props.points.length - 1, Math.max(0, i));
}
const last = computed(() => props.points.length - 1);
const total = computed(() => props.points.reduce((n, p) => n + p.value, 0));
</script>

<template>
    <figure class="relative">
        <svg
            ref="svg"
            :viewBox="`0 0 ${W} ${H}`"
            class="h-auto w-full overflow-visible"
            role="img"
            :aria-label="`${label}: ${formatNumber(total)}`"
            @pointermove="move"
            @pointerleave="hover = null"
        >
            <!-- Recessive grid and y labels -->
            <g v-for="tick in ticks" :key="tick">
                <line :x1="pad.left" :x2="W - pad.right" :y1="y(tick)" :y2="y(tick)" class="stroke-neutral-200 dark:stroke-white/10" stroke-width="1" />
                <text :x="pad.left - 8" :y="y(tick) + 4" text-anchor="end" class="fill-neutral-400 text-[11px]">{{ formatNumber(tick) }}</text>
            </g>
            <text v-for="tick in dateTicks" :key="tick.i" :x="x(tick.i)" :y="H - 6" :text-anchor="tick.i === 0 ? 'start' : tick.i === last ? 'end' : 'middle'" class="fill-neutral-400 text-[11px]">{{ tick.label }}</text>

            <path :d="area" class="fill-gold-400/15" />
            <path :d="line" fill="none" class="stroke-gold-500 dark:stroke-gold-400" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

            <!-- Latest value, always labelled -->
            <circle :cx="x(last)" :cy="y(points[last].value)" r="4.5" class="fill-gold-500 stroke-white dark:fill-gold-400 dark:stroke-ink-900" stroke-width="2" />

            <!-- Crosshair -->
            <g v-if="hover !== null">
                <line :x1="x(hover)" :x2="x(hover)" :y1="pad.top" :y2="y(0)" class="stroke-neutral-400 dark:stroke-white/30" stroke-dasharray="3 3" />
                <circle :cx="x(hover)" :cy="y(points[hover].value)" r="5" class="fill-gold-500 stroke-white dark:fill-gold-400 dark:stroke-ink-900" stroke-width="2" />
            </g>
        </svg>

        <div
            v-if="hover !== null"
            class="pointer-events-none absolute top-0 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-xs shadow-lg dark:border-white/10 dark:bg-ink-800"
            :style="{ left: `${(x(hover) / W) * 100}%`, transform: `translateX(${hover > points.length / 2 ? '-105%' : '5%'})` }"
        >
            <p class="text-neutral-500 dark:text-neutral-400">{{ formatDate(points[hover].date, { weekday: 'short', day: 'numeric', month: 'short' }) }}</p>
            <p class="font-semibold text-neutral-900 dark:text-white">{{ formatNumber(points[hover].value) }} {{ unit || label }}</p>
        </div>
    </figure>
</template>
