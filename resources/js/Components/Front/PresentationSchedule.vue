<script setup>
import { computed, ref } from 'vue';
import { useI18n } from '@/i18n';
import { mockTracks, presentationBlocks, presentationRooms, presentationSessions } from '@/data/mock';

/*
 * Public presentation schedule: sessions per time block, each room with its track, chair and running order.
 * Same data as the backend Presentations menu (mock), so presenters can find their slot.
 */
const { t, tr, formatDate } = useI18n();

const block = ref(presentationBlocks[0].id);
const q = ref('');

const toMinutes = (hhmm) => Number(hhmm.slice(0, 2)) * 60 + Number(hhmm.slice(3));
const toTime = (minutes) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
const blockOf = (id) => presentationBlocks.find((b) => b.id === id);
const startOf = (session, i) => toTime(toMinutes(blockOf(session.block).start) + i * session.minutes);
const roomOf = (id) => presentationRooms.find((r) => r.id === id);
const trackOf = (id) => mockTracks.find((x) => x.id === id);

// Searching looks across every block, so a presenter finds their slot without guessing the session
const needle = computed(() => q.value.trim().toLowerCase());
const matches = (paper) => `${paper.code} ${paper.title.en} ${paper.title.th} ${paper.author.en} ${paper.author.th}`.toLowerCase().includes(needle.value);
const sessions = computed(() => presentationSessions
    .filter((s) => (needle.value ? s.papers.some(matches) : s.block === block.value))
    .sort((a, b) => a.block - b.block || a.room - b.room));
</script>

<template>
    <section id="presentations" class="mx-auto max-w-[1600px] scroll-mt-28 px-4 pb-12 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gold-400/20 bg-ink-900/80 p-5 sm:p-6">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-semibold text-gold-400">
                        <Fa icon="person-chalkboard" /> {{ t('schedule.presentations.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-400">{{ t('schedule.presentations.lead', { date: formatDate('2027-01-28', { day: 'numeric', month: 'long', year: 'numeric' }) }) }}</p>
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

            <!-- Time blocks (hidden while searching: results come from every block) -->
            <Transition name="fade" mode="out-in">
                <div v-if="!needle" class="mb-5 flex flex-wrap gap-2">
                    <button
                        v-for="item in presentationBlocks"
                        :key="item.id"
                        :class="block === item.id ? 'border-gold-400 bg-gold-400 text-black' : 'border-white/15 text-neutral-200 hover:border-gold-400'"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition"
                        @click="block = item.id"
                    >
                        {{ tr(item.label) }} · {{ item.start }}–{{ item.end }}
                    </button>
                </div>
                <p v-else class="mb-5 text-sm text-neutral-400">{{ t('schedule.presentations.results', { count: sessions.reduce((n, s) => n + s.papers.filter(matches).length, 0) }) }}</p>
            </Transition>

            <Transition name="fade" mode="out-in">
                <div :key="needle ? `q-${needle}` : `b-${block}`" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
                    <article v-for="session in sessions" :key="session.id" class="rounded-xl border border-white/10 bg-black/30">
                        <header class="border-b border-white/10 p-4">
                            <p class="font-semibold text-white"><Fa icon="location-dot" class="mr-1.5 text-gold-400" />{{ tr(roomOf(session.room).name) }}</p>
                            <p class="mt-1 text-sm text-gold-300">{{ tr(trackOf(session.track).name) }}</p>
                            <p class="mt-1 text-xs text-neutral-400">
                                <template v-if="needle">{{ tr(blockOf(session.block).label) }} {{ blockOf(session.block).start }}–{{ blockOf(session.block).end }} · </template>
                                {{ t('admin.presentations.chair') }}: {{ tr(session.chair) || '—' }}
                            </p>
                        </header>
                        <ol class="divide-y divide-white/5">
                            <li
                                v-for="(paper, i) in session.papers"
                                v-show="!needle || matches(paper)"
                                :key="paper.code"
                                :class="needle && matches(paper) ? 'bg-gold-400/10' : ''"
                                class="flex gap-3 px-4 py-3 text-sm"
                            >
                                <span class="w-11 shrink-0 font-mono text-xs text-gold-300">{{ startOf(session, i) }}</span>
                                <div class="min-w-0">
                                    <p class="text-neutral-100">{{ tr(paper.title) }}</p>
                                    <p class="mt-0.5 text-xs text-neutral-400">
                                        {{ tr(paper.author) }} · <span class="font-mono">{{ paper.code }}</span>
                                        <span v-if="paper.mode === 'online'" class="ml-1 rounded-full bg-sky-500/15 px-2 py-0.5 text-sky-300"><Fa icon="video" class="mr-1" />{{ t('admin.presentations.online') }}</span>
                                    </p>
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
