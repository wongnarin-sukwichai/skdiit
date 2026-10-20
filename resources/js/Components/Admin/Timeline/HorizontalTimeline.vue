<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/i18n';
import { addDays, fromDay, phaseState, spanDays, toDay, today as todayIn } from '@/utils/timeline';

/*
 * A horizontal timeline: one row per phase on a shared day scale, with a "today" line. "Fit" squeezes the whole plan
 * into the width; "Days" gives every day a column for exact dates. Rows are buttons: the parent opens its editor.
 * Phases: { key, icon, label, sub?, start, end, mode: lock|warn|info, milestone?, public? }
 */
const props = defineProps({
    phases: { type: Array, required: true },
    issues: { type: Object, default: () => ({}) }, // phase key => 'error' | 'warn'
    today: { type: String, default: () => todayIn() },
});
const emit = defineEmits(['select']);

const { t, formatDate } = useI18n();

const zoom = ref('fit');
const DETAIL_PX = 28;

// The scale starts on the 1st of the first month and runs a little past the last phase
const range = computed(() => {
    const starts = props.phases.map((p) => p.start).sort();
    const ends = props.phases.map((p) => p.end).sort();
    const first = `${starts[0].slice(0, 7)}-01`;
    return { start: first, end: addDays(ends[ends.length - 1], 7) };
});
const days = computed(() => toDay(range.value.end) - toDay(range.value.start) + 1);

// Width left for the day scale once the sticky name column is placed
const scroller = ref(null);
const label = ref(null);
const available = ref(800);
let observer;
function measure() {
    if (scroller.value && label.value) available.value = scroller.value.clientWidth - label.value.offsetWidth;
}
onMounted(() => {
    measure();
    observer = new ResizeObserver(measure);
    observer.observe(scroller.value);
});
onBeforeUnmount(() => observer?.disconnect());

const px = computed(() => (zoom.value === 'fit' ? Math.max(3, available.value / days.value) : DETAIL_PX));
const width = computed(() => days.value * px.value);
const x = (date) => (toDay(date) - toDay(range.value.start)) * px.value;

const months = computed(() => {
    const list = [];
    let month = range.value.start;
    while (month <= range.value.end) {
        list.push({ date: month, left: x(month) });
        const [y, m] = month.split('-').map(Number);
        month = m === 12 ? `${y + 1}-01-01` : `${y}-${String(m + 1).padStart(2, '0')}-01`;
    }
    return list;
});
const dayCells = computed(() => (zoom.value === 'fit' ? [] : Array.from({ length: days.value }, (_, i) => {
    const date = fromDay(toDay(range.value.start) + i);
    return { date, day: +date.slice(8), weekend: [0, 6].includes(new Date(`${date}T00:00:00Z`).getUTCDay()) };
})));

const showToday = computed(() => props.today >= range.value.start && props.today <= range.value.end);
const todayLeft = computed(() => x(props.today) + px.value / 2);

// Day columns as a background, so the rows stay light
const grid = computed(() => (zoom.value === 'fit' ? {} : {
    backgroundImage: 'linear-gradient(to right, rgb(128 128 128 / .12) 1px, transparent 1px)',
    backgroundSize: `${px.value}px 100%`,
}));

// The day view opens at today (or the plan's start when today is outside it)
watch(zoom, async (value) => {
    await nextTick();
    if (value === 'detail' && scroller.value) {
        const at = showToday.value ? props.today : range.value.start;
        scroller.value.scrollLeft = Math.max(0, x(at) - 3 * DETAIL_PX);
    }
});

const STRIPES = 'repeating-linear-gradient(135deg, rgb(245 184 0 / .55) 0 5px, rgb(245 184 0 / .18) 5px 10px)';
function barStyle(phase) {
    const style = { left: `${x(phase.start)}px`, width: `${Math.max(px.value, spanDays(phase) * px.value)}px` };
    if (phase.mode === 'warn') style.backgroundImage = STRIPES;
    return style;
}
const barClass = (phase) => ({
    lock: 'border-gold-500 bg-gold-400',
    warn: 'border-gold-500',
    info: 'border-gold-400 border-dashed bg-gold-400/10',
})[phase.mode];

const issueRing = (key) => ({ error: 'ring-2 ring-red-500 ring-offset-1 dark:ring-offset-ink-900', warn: 'ring-2 ring-amber-500 ring-offset-1 dark:ring-offset-ink-900' })[props.issues[key]] ?? '';
const dateText = (p) => (p.start === p.end ? formatDate(p.start) : `${formatDate(p.start, { day: 'numeric', month: 'short' })} – ${formatDate(p.end)}`);
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Legend -->
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-600 dark:text-neutral-400">
                <li class="flex items-center gap-1.5"><span class="h-3 w-6 rounded-sm border border-gold-500 bg-gold-400" /> {{ t('admin.timeline.mode.lock') }}</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-6 rounded-sm border border-gold-500" :style="{ backgroundImage: STRIPES }" /> {{ t('admin.timeline.mode.warn') }}</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-6 rounded-sm border border-dashed border-gold-400 bg-gold-400/10" /> {{ t('admin.timeline.mode.info') }}</li>
                <li class="flex items-center gap-1.5"><span class="size-2.5 rotate-45 bg-gold-500" /> {{ t('admin.timeline.milestone') }}</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-0.5 bg-red-500" /> {{ t('admin.timeline.today') }}</li>
                <li class="flex items-center gap-1.5"><Fa icon="circle-check" class="text-emerald-500" /> {{ t('admin.timeline.realBadge') }}</li>
            </ul>
            <div class="flex rounded-lg border border-neutral-200 p-0.5 text-xs dark:border-white/10" role="group">
                <button
                    v-for="mode in ['fit', 'detail']"
                    :key="mode"
                    type="button"
                    :aria-pressed="zoom === mode"
                    :class="zoom === mode ? 'bg-gold-400 text-black' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    class="rounded-md px-3 py-1.5 font-medium transition"
                    @click="zoom = mode"
                >
                    {{ t(`admin.timeline.zoom.${mode}`) }}
                </button>
            </div>
        </div>

        <div ref="scroller" class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <div :style="{ width: `calc(${width}px + var(--label))` }" class="min-w-full [--label:11rem] sm:[--label:15rem]">
                <!-- Scale: months, then day numbers in the day view -->
                <div class="flex border-b border-neutral-200 bg-neutral-50 dark:border-white/10 dark:bg-ink-850">
                    <div ref="label" class="sticky left-0 z-20 w-(--label) shrink-0 border-r border-neutral-200 bg-neutral-50 px-3 py-2 text-xs font-semibold text-neutral-500 dark:border-white/10 dark:bg-ink-850 dark:text-neutral-400">
                        {{ t('admin.timeline.phase') }}
                    </div>
                    <div class="relative shrink-0" :class="zoom === 'fit' ? 'h-9' : 'h-14'" :style="{ width: `${width}px` }">
                        <span
                            v-for="m in months"
                            :key="m.date"
                            class="absolute top-0 border-l border-neutral-300 pt-2 pl-1.5 text-xs font-semibold whitespace-nowrap text-neutral-600 dark:border-white/15 dark:text-neutral-300"
                            :style="{ left: `${m.left}px` }"
                        >{{ formatDate(m.date, { month: 'short', year: '2-digit' }) }}</span>
                        <span
                            v-for="d in dayCells"
                            :key="d.date"
                            :class="[d.weekend ? 'text-neutral-400 dark:text-neutral-500' : 'text-neutral-600 dark:text-neutral-300', d.date === today ? 'rounded bg-red-500 font-bold text-white!' : '']"
                            class="absolute bottom-1 text-center text-[11px] tabular-nums"
                            :style="{ left: `${x(d.date)}px`, width: `${px}px` }"
                        >{{ d.day }}</span>
                        <span
                            v-if="showToday && zoom === 'fit'"
                            class="absolute bottom-0 h-2 w-0.5 bg-red-500"
                            :style="{ left: `${todayLeft}px` }"
                        />
                    </div>
                </div>

                <!-- One row per phase -->
                <button
                    v-for="phase in phases"
                    :key="phase.key"
                    type="button"
                    class="group flex w-full border-b border-neutral-100 text-left last:border-b-0 hover:bg-gold-50/60 dark:border-white/5 dark:hover:bg-gold-400/5"
                    @click="emit('select', phase.key)"
                >
                    <div class="sticky left-0 z-10 w-(--label) shrink-0 border-r border-neutral-200 bg-white px-3 py-2 group-hover:bg-gold-50 dark:border-white/10 dark:bg-ink-900 dark:group-hover:bg-ink-850">
                        <p class="flex items-center gap-2 text-sm font-medium" :class="phaseState(phase, today) === 'done' ? 'text-neutral-400 dark:text-neutral-500' : ''">
                            <Fa :icon="phase.icon" class="w-4 shrink-0 text-gold-500" />
                            <span class="truncate">{{ phase.label }}</span>
                            <Fa v-if="phase.real" icon="circle-check" class="shrink-0 text-xs text-emerald-500" :title="t('admin.timeline.realBadge')" />
                            <Fa v-if="issues[phase.key] === 'error'" icon="circle-exclamation" class="shrink-0 text-red-500" />
                            <Fa v-else-if="issues[phase.key] === 'warn'" icon="triangle-exclamation" class="shrink-0 text-amber-500" />
                        </p>
                        <p class="mt-0.5 truncate pl-6 text-xs text-neutral-500 tabular-nums dark:text-neutral-400">{{ dateText(phase) }}</p>
                    </div>

                    <div class="relative h-14 shrink-0" :style="{ width: `${width}px`, ...grid }">
                        <span v-for="m in months" :key="m.date" class="absolute inset-y-0 border-l border-neutral-200 dark:border-white/10" :style="{ left: `${m.left}px` }" />

                        <!-- A single day is a diamond, a period a bar -->
                        <span
                            v-if="phase.milestone"
                            :title="`${phase.label}: ${dateText(phase)}`"
                            :class="[issueRing(phase.key), phaseState(phase, today) === 'done' ? 'opacity-40' : '']"
                            class="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 border border-gold-600 bg-gold-500 transition group-hover:scale-125"
                            :style="{ left: `${x(phase.start) + px / 2}px` }"
                        />
                        <span
                            v-else
                            :title="`${phase.label}: ${dateText(phase)}`"
                            :class="[barClass(phase), issueRing(phase.key), phaseState(phase, today) === 'done' ? 'opacity-40' : '', phaseState(phase, today) === 'active' ? 'shadow-[0_0_12px_rgba(245,184,0,.6)]' : '']"
                            class="absolute top-1/2 flex h-5 -translate-y-1/2 items-center justify-end overflow-hidden rounded-md border px-1 transition group-hover:brightness-110"
                            :style="barStyle(phase)"
                        >
                            <Fa v-if="phase.mode === 'lock' && spanDays(phase) * px > 18" icon="lock" class="text-[10px] text-black/70" />
                        </span>

                        <span v-if="showToday" class="pointer-events-none absolute inset-y-0 w-0.5 bg-red-500/80" :style="{ left: `${todayLeft}px` }" />
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
