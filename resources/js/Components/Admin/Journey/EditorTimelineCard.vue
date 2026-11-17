<script setup>
import { toRef } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';
import { useEditorStages } from '@/composables/useEditorStages';

/*
 * The editor's work along the conference schedule, in full, on the editor/admin home (2026-11-12): each stage with its
 * dates, the days left or until it starts, and the papers still waiting, linking to that work. Every other backend
 * page shows the same stages as a slim bar (EditorTimeline in AdminLayout).
 */
const props = defineProps({
    data: { type: Object, required: true }, // shared `editorStages` (App\Support\EditorStages)
});

const { t, formatDate, formatNumber } = useI18n();
const { accessTo } = useModules();
const { stages, active, daysLeft, daysTo } = useEditorStages(toRef(props, 'data'));

const range = (s) => (s.start === s.end ? formatDate(s.start) : `${formatDate(s.start, { day: 'numeric', month: 'short' })} – ${formatDate(s.end)}`);
const box = (s) => {
    if (s.late) return 'border-red-300 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10';
    if (s.state === 'active') return 'border-gold-400 bg-gold-50 ring-2 ring-gold-400/30 dark:bg-gold-400/10';
    if (s.state === 'done') return 'border-neutral-200 bg-neutral-50 opacity-70 dark:border-white/10 dark:bg-white/5';
    return 'border-neutral-200 dark:border-white/10';
};
const dot = (s) => (s.late ? 'bg-red-500 text-white' : s.state === 'active' ? 'bg-gold-400 text-black' : s.state === 'done' ? 'bg-emerald-500 text-white' : 'bg-neutral-200 text-neutral-500 dark:bg-white/10 dark:text-neutral-300');
</script>

<template>
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-semibold"><Fa icon="list-check" class="text-gold-500" /> {{ t('admin.editorTimeline.title') }}</h2>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    <template v-if="active.length">{{ t('admin.editorTimeline.now', { names: active.map((s) => t(`admin.editorTimeline.stage.${s.key}`)).join(', ') }) }}</template>
                    <template v-else>{{ t('admin.editorTimeline.nothingNow') }}</template>
                </p>
            </div>
            <Link v-if="accessTo('settings')" :href="$url('/admin/settings?tab=schedule')" class="text-xs text-gold-600 hover:underline dark:text-gold-400"><Fa icon="calendar-days" class="mr-1" />{{ t('admin.editorTimeline.schedule') }}</Link>
        </div>

        <ol class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
            <li v-for="(s, i) in stages" :key="s.key">
                <Link :href="$url(s.href)" :class="box(s)" class="flex h-full flex-col gap-2 rounded-xl border p-3 transition hover:border-gold-400">
                    <div class="flex items-center gap-2">
                        <span :class="dot(s)" class="grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold">
                            <Fa v-if="s.state === 'done' && !s.late" icon="check" />
                            <template v-else>{{ i + 1 }}</template>
                        </span>
                        <span class="text-sm leading-tight font-semibold">{{ t(`admin.editorTimeline.stage.${s.key}`) }}</span>
                    </div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="calendar-days" class="mr-1" />{{ range(s) }}</p>
                    <p v-if="s.late" class="text-xs font-medium text-red-600 dark:text-red-400"><Fa icon="triangle-exclamation" class="mr-1" />{{ t('admin.editorTimeline.late') }}</p>
                    <p v-else-if="s.state === 'active'" class="text-xs font-medium text-gold-700 dark:text-gold-300"><Fa icon="hourglass-half" class="mr-1" />{{ t('admin.editorTimeline.daysLeft', { days: daysLeft(s) }) }}</p>
                    <p v-else-if="s.state === 'upcoming'" class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.editorTimeline.startsIn', { days: daysTo(s) }) }}</p>
                    <p v-else class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.editorTimeline.ended') }}</p>
                    <p class="mt-auto flex items-baseline gap-1.5 border-t border-neutral-200 pt-2 dark:border-white/10">
                        <span :class="s.count ? (s.late ? 'text-red-600 dark:text-red-400' : 'text-neutral-900 dark:text-white') : 'text-neutral-400'" class="text-xl font-semibold">{{ formatNumber(s.count) }}</span>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ t(`admin.editorTimeline.count.${s.key}`) }}</span>
                    </p>
                    <p v-if="s.revising" class="-mt-1 text-xs text-orange-600 dark:text-orange-300"><Fa icon="pen" class="mr-1" />{{ t('admin.editorTimeline.revising', { n: formatNumber(s.revising) }) }}</p>
                </Link>
            </li>
        </ol>
    </section>
</template>
