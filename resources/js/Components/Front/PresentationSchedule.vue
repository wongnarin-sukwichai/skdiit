<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

/*
 * Public presentation schedule (2026-11-12, real: PresentationController::publicSchedule shared by the /schedule route):
 * sessions per day and time span, each room with its track, chair and running order, so presenters find their slot.
 */
const { t, tr, formatDate } = useI18n();
const all = computed(() => usePage().props.presentations ?? []);

// Days and time spans to pick from
const slots = computed(() => [...new Set(all.value.map((s) => `${s.date}|${s.start}|${s.end}`))].sort()
    .map((key) => { const [date, start, end] = key.split('|'); return { key, date, start, end }; }));
const picked = ref(null);
const slot = computed(() => picked.value ?? slots.value[0]?.key ?? null);
const q = ref('');

// Searching looks across every session, so a presenter finds their slot without guessing
const needle = computed(() => q.value.trim().toLowerCase());
const matches = (paper) => `${paper.code} ${paper.title.en} ${paper.title.th} ${paper.authors}`.toLowerCase().includes(needle.value);
const sessions = computed(() => all.value
    .filter((s) => (needle.value ? s.papers.some(matches) : `${s.date}|${s.start}|${s.end}` === slot.value)));
const days = computed(() => [...new Set(all.value.map((s) => s.date))].sort());
const lead = computed(() => days.value.map((d) => formatDate(d, { day: 'numeric', month: 'long', year: 'numeric' })).join(', '));
</script>

<template>
    <section v-if="all.length" id="presentations" class="mx-auto max-w-[1600px] scroll-mt-28 px-4 pb-12 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gold-400/20 bg-ink-900/80 p-5 sm:p-6">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-semibold text-gold-400">
                        <Fa icon="person-chalkboard" /> {{ t('schedule.presentations.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-400">{{ t('schedule.presentations.lead', { date: lead }) }}</p>
                </div>
                <label class="relative w-full sm:w-80">
                    <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-500" />
                    <input
                        v-model="q"
                        :placeholder="t('schedule.presentations.search')"
                        class="w-full rounded-lg border border-white/10 bg-ink-800 py-2.5 pr-3 pl-11 text-sm text-white outline-none placeholder:text-neutral-500 focus:border-gold-400"
                    >
                </label>
            </div>

            <!-- Day and time span (hidden while searching: results come from every session) -->
            <Transition name="fade" mode="out-in">
                <div v-if="!needle" class="mb-5 flex flex-wrap gap-2">
                    <button
                        v-for="item in slots"
                        :key="item.key"
                        :class="slot === item.key ? 'border-gold-400 bg-gold-400 text-black' : 'border-white/15 text-neutral-200 hover:border-gold-400'"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition"
                        @click="picked = item.key"
                    >
                        <template v-if="days.length > 1">{{ formatDate(item.date, { day: 'numeric', month: 'short' }) }} · </template>{{ item.start }}–{{ item.end }}
                    </button>
                </div>
                <p v-else class="mb-5 text-sm text-neutral-400">{{ t('schedule.presentations.results', { count: sessions.reduce((n, s) => n + s.papers.filter(matches).length, 0) }) }}</p>
            </Transition>

            <Transition name="fade" mode="out-in">
                <div :key="needle ? `q-${needle}` : `s-${slot}`" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
                    <article v-for="session in sessions" :key="session.id" class="rounded-xl border border-white/10 bg-black/30">
                        <header class="border-b border-white/10 p-4">
                            <p class="font-semibold text-white"><Fa icon="location-dot" class="mr-1.5 text-gold-400" />{{ tr(session.room.name) }}</p>
                            <p v-if="session.track" class="mt-1 text-sm text-gold-300">{{ tr(session.track.name) }}</p>
                            <p class="mt-1 text-xs text-neutral-400">
                                <template v-if="needle">{{ formatDate(session.date, { day: 'numeric', month: 'short' }) }} {{ session.start }}–{{ session.end }} · </template>
                                {{ t('admin.presentations.chair') }}: {{ session.chair || '—' }}
                            </p>
                        </header>
                        <ol class="divide-y divide-white/5">
                            <li
                                v-for="paper in session.papers"
                                v-show="!needle || matches(paper)"
                                :key="paper.id"
                                :class="needle && matches(paper) ? 'bg-gold-400/10' : ''"
                                class="flex gap-3 px-4 py-3 text-sm"
                            >
                                <span class="w-11 shrink-0 font-mono text-xs text-gold-300">{{ paper.at }}</span>
                                <div class="min-w-0">
                                    <p class="text-neutral-100">{{ tr(paper.title) }}</p>
                                    <p class="mt-0.5 text-xs text-neutral-400">{{ paper.authors }} · <span class="font-mono">{{ paper.code }}</span></p>
                                </div>
                            </li>
                        </ol>
                    </article>
                    <p v-if="!sessions.length" class="col-span-full py-10 text-center text-neutral-400">{{ t('schedule.presentations.empty') }}</p>
                </div>
            </Transition>
        </div>
    </section>
</template>
