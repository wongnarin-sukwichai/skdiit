<script setup>
import { toRef } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';
import { useEditorStages } from '@/composables/useEditorStages';

/*
 * The editor's work along the conference schedule (2026-11-12): a slim bar above every backend page but the home for
 * admin and editors (AdminLayout, shared `editorStages` = App\Support\EditorStages; the home shows EditorTimelineCard). Seven stages with their dates from
 * Settings › กำหนดการ and the papers still waiting at each; the stage running today is gold with the days left,
 * a stage past its dates with papers still waiting is red. Each stage links to that work; hover shows the dates.
 */
const props = defineProps({
    data: { type: Object, required: true }, // { queues, stages, schedule }
});

const { t, formatDate, formatNumber } = useI18n();
const { accessTo } = useModules();

const { stages, active, next, daysLeft, daysTo } = useEditorStages(toRef(props, 'data'));
const label = (s) => t(`admin.editorTimeline.stage.${s.key}`);
const range = (s) => (s.start === s.end ? formatDate(s.start) : `${formatDate(s.start, { day: 'numeric', month: 'short' })} – ${formatDate(s.end)}`);
const tip = (s) => [label(s), range(s), `${formatNumber(s.count)} ${t(`admin.editorTimeline.count.${s.key}`)}`, s.revising ? t('admin.editorTimeline.revising', { n: formatNumber(s.revising) }) : null].filter(Boolean).join(' · ');

const chip = (s) => {
    if (s.late) return 'border-red-300 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300';
    if (s.state === 'active') return 'border-gold-400 bg-gold-100 font-semibold text-gold-800 dark:bg-gold-400/15 dark:text-gold-200';
    if (s.state === 'done') return 'border-transparent text-neutral-400 dark:text-neutral-500';
    return 'border-transparent text-neutral-600 dark:text-neutral-300';
};
const dot = (s) => (s.late ? 'bg-red-500 text-white' : s.state === 'active' ? 'bg-gold-400 text-black' : s.state === 'done' ? 'bg-emerald-500 text-white' : 'bg-neutral-200 text-neutral-600 dark:bg-white/10 dark:text-neutral-300');
</script>

<template>
    <nav :aria-label="t('admin.editorTimeline.title')" class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-2 py-1.5 text-xs dark:border-white/10 dark:bg-ink-900">
        <span class="hidden shrink-0 items-center gap-1.5 pr-1 pl-1 font-semibold text-neutral-500 xl:flex dark:text-neutral-400" :title="t('admin.editorTimeline.title')">
            <Fa icon="list-check" class="text-gold-500" />
        </span>

        <ol class="flex min-w-0 flex-1 items-center gap-0.5 overflow-x-auto">
            <li v-for="(s, i) in stages" :key="s.key" class="flex shrink-0 items-center">
                <Fa v-if="i" icon="chevron-right" class="mx-0.5 text-[9px] text-neutral-300 dark:text-white/20" />
                <Link :href="$url(s.href)" :title="tip(s)" :class="chip(s)" class="flex items-center gap-1.5 rounded-full border px-2 py-1 transition hover:border-gold-400">
                    <span :class="dot(s)" class="grid size-4.5 place-items-center rounded-full text-[10px] font-semibold">
                        <Fa v-if="s.state === 'done' && !s.late" icon="check" class="text-[9px]" />
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <!-- Small screens: only the running stage spells its name -->
                    <span :class="s.state === 'active' || s.late ? '' : 'hidden lg:inline'" class="whitespace-nowrap">{{ label(s) }}</span>
                    <span
                        v-if="s.count"
                        :class="s.late ? 'bg-red-500 text-white' : s.state === 'active' ? 'bg-gold-500 text-black' : 'bg-neutral-200 text-neutral-700 dark:bg-white/15 dark:text-neutral-200'"
                        class="rounded-full px-1.5 text-[10px] leading-4 font-semibold"
                    >{{ formatNumber(s.count) }}</span>
                </Link>
            </li>
        </ol>

        <!-- Where we are: days left in the running stage, or when the next one starts -->
        <span class="hidden shrink-0 items-center gap-1 border-l border-neutral-200 pl-2 whitespace-nowrap text-neutral-500 md:flex dark:border-white/10 dark:text-neutral-400">
            <template v-if="active.length"><Fa icon="hourglass-half" class="text-gold-500" />{{ t('admin.editorTimeline.daysLeftShort', { days: daysLeft(active[0]) }) }}</template>
            <template v-else-if="next">{{ t('admin.editorTimeline.nextIn', { name: label(next), days: daysTo(next) }) }}</template>
            <template v-else>{{ t('admin.editorTimeline.allDone') }}</template>
        </span>
        <Link v-if="accessTo('settings')" :href="$url('/admin/settings?tab=schedule')" :title="t('admin.editorTimeline.schedule')" class="grid size-7 shrink-0 place-items-center rounded-md text-neutral-400 transition hover:bg-neutral-100 hover:text-gold-500 dark:hover:bg-white/5">
            <Fa icon="calendar-days" />
        </Link>
    </nav>
</template>
