<script setup>
import { computed, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { useModules } from '@/composables/useModules';
import { mockTracks, presentationBlocks, presentationRooms, presentationSessions, unscheduledPapers } from '@/data/mock';

/*
 * Presentation schedule (admin, editor): sessions per time block and room, each with one track,
 * a chair and papers in running order. Mock until wired to real data (submissions.presentation_at / room).
 */
const { t, tr, formatNumber } = useI18n();
const { toast } = useToast();
// Editors see the schedule; only admin arranges sessions
const canEdit = useModules().accessTo('presentations') === 'full';

// Mock phase: the shared sample lists, so the public Schedule page shows these edits until the browser reloads
const sessions = reactive(presentationSessions);
const waiting = reactive(unscheduledPapers);

const roomOf = (id) => presentationRooms.find((r) => r.id === id);
const trackOf = (id) => mockTracks.find((x) => x.id === id);
const blockOf = (id) => presentationBlocks.find((b) => b.id === id);
const toMinutes = (hhmm) => Number(hhmm.slice(0, 2)) * 60 + Number(hhmm.slice(3));
const toTime = (minutes) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
const blockLength = (block) => toMinutes(block.end) - toMinutes(block.start);
const used = (session) => session.papers.length * session.minutes;
const startOf = (session, i) => toTime(toMinutes(blockOf(session.block).start) + i * session.minutes);
const sessionsIn = (block) => sessions.filter((s) => s.block === block.id).sort((a, b) => a.room - b.room);

const stats = computed(() => ({
    sessions: sessions.length,
    scheduled: sessions.reduce((n, s) => n + s.papers.length, 0),
    unscheduled: waiting.length,
    online: sessions.reduce((n, s) => n + s.papers.filter((p) => p.mode === 'online').length, 0),
}));
const statCards = [
    { key: 'sessions', icon: 'person-chalkboard' },
    { key: 'scheduled', icon: 'calendar-days' },
    { key: 'unscheduled', icon: 'hourglass-half' },
    { key: 'online', icon: 'video' },
];

// ---- Running order ----
function move(session, i, step) {
    const [paper] = session.papers.splice(i, 1);
    session.papers.splice(i + step, 0, paper);
}
function unschedule(session, i) {
    waiting.push(...session.papers.splice(i, 1));
}
function schedule(paper, sessionId) {
    if (!sessionId) return;
    const session = sessions.find((s) => s.id === Number(sessionId));
    session.papers.push(...waiting.splice(waiting.indexOf(paper), 1));
    toast('admin.presentations.toast.added', { code: paper.code, room: tr(roomOf(session.room).name) });
}

// ---- Add / edit a session ----
const editing = ref(null); // { id?, block, room, track, chair, minutes }
const editorError = ref('');
function openEditor(session = null) {
    editorError.value = '';
    editing.value = session
        ? { id: session.id, block: session.block, room: session.room, track: session.track, chair: tr(session.chair), minutes: session.minutes }
        : { block: presentationBlocks[0].id, room: presentationRooms[0].id, track: mockTracks[0].id, chair: '', minutes: 15 };
}
function saveSession() {
    const data = editing.value;
    // One session per room in each time block
    if (sessions.some((s) => s.block === data.block && s.room === data.room && s.id !== data.id)) {
        editorError.value = 'admin.presentations.roomTaken';
        return;
    }
    const fields = { block: data.block, room: data.room, track: data.track, minutes: Number(data.minutes), chair: { en: data.chair, th: data.chair } };
    if (data.id) {
        Object.assign(sessions.find((s) => s.id === data.id), fields);
    } else {
        sessions.push({ id: Math.max(0, ...sessions.map((s) => s.id)) + 1, papers: [], ...fields });
    }
    toast('admin.presentations.toast.saved');
    editing.value = null;
}
function removeSession() {
    const session = sessions.find((s) => s.id === editing.value.id);
    waiting.push(...session.papers);
    sessions.splice(sessions.indexOf(session), 1);
    toast('admin.presentations.toast.removed');
    editing.value = null;
}

const iconBtn = 'grid size-7 place-items-center rounded-md text-neutral-400 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-25 disabled:hover:bg-transparent dark:hover:bg-white/10';
const field = 'w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400';
</script>

<template>
    <Head :title="t('admin.menu.presentations')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="person-chalkboard" :title="t('admin.menu.presentations')" :subtitle="t('admin.presentations.subtitle')" />
            <StatCards :stats="stats" :cards="statCards" />
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20" @click="toast('admin.presentations.toast.printed')">
                    <Fa icon="print" /> {{ t('admin.presentations.print') }}
                </button>
                <button v-if="canEdit" type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="openEditor()">
                    <Fa icon="plus" /> {{ t('admin.presentations.addSession') }}
                </button>
            </div>
        </Card>

        <!-- One card per time block; sessions side by side by room -->
        <Card v-for="block in presentationBlocks" :key="block.id" class="space-y-4">
            <h2 class="flex flex-wrap items-baseline gap-x-3 text-lg font-semibold">
                <span><Fa icon="clock" class="mr-2 text-gold-500" />{{ tr(block.label) }}</span>
                <span class="text-sm font-normal text-neutral-500 dark:text-neutral-400">{{ block.start }} – {{ block.end }} · {{ t('admin.presentations.minutes', { n: blockLength(block) }) }}</span>
            </h2>

            <TransitionGroup name="fade" tag="div" class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-4">
                <section v-for="session in sessionsIn(block)" :key="session.id" class="flex flex-col rounded-xl border border-neutral-200 dark:border-white/10">
                    <header class="space-y-1 border-b border-neutral-200 p-4 dark:border-white/10">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold"><Fa icon="location-dot" class="mr-1.5 text-gold-500" />{{ tr(roomOf(session.room).name) }}</p>
                            <button v-if="canEdit" :class="iconBtn" :title="t('admin.presentations.editSession')" @click="openEditor(session)"><Fa icon="pen" class="text-xs" /></button>
                        </div>
                        <p class="text-sm text-gold-700 dark:text-gold-300">{{ tr(trackOf(session.track).name) }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.presentations.chair') }}: {{ tr(session.chair) || '—' }}</p>
                        <!-- Time used in this block -->
                        <div class="pt-1">
                            <div class="h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10">
                                <div
                                    :class="used(session) > blockLength(block) ? 'bg-red-500' : 'bg-gold-400'"
                                    class="h-full rounded-full transition-all"
                                    :style="{ width: `${Math.min(100, (used(session) / blockLength(block)) * 100)}%` }"
                                />
                            </div>
                            <p :class="used(session) > blockLength(block) ? 'text-red-600 dark:text-red-400' : 'text-neutral-500 dark:text-neutral-400'" class="mt-1 text-xs">
                                {{ t('admin.presentations.usage', { used: used(session), total: blockLength(block), each: session.minutes }) }}
                                <template v-if="used(session) > blockLength(block)"> · {{ t('admin.presentations.overtime') }}</template>
                            </p>
                        </div>
                    </header>
                    <TransitionGroup name="fade" tag="ol" class="flex-1 divide-y divide-neutral-100 dark:divide-white/5">
                        <li v-for="(paper, i) in session.papers" :key="paper.code" class="flex gap-3 px-4 py-2.5 text-sm">
                            <span class="w-11 shrink-0 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ startOf(session, i) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2">{{ tr(paper.title) }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                    <span class="font-mono">{{ paper.code }}</span> · {{ tr(paper.author) }}
                                    <span v-if="paper.mode === 'online'" class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"><Fa icon="video" class="mr-1" />{{ t('admin.presentations.online') }}</span>
                                </p>
                            </div>
                            <div v-if="canEdit" class="flex shrink-0 flex-col">
                                <button :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(session, i, -1)"><Fa icon="chevron-up" class="text-xs" /></button>
                                <button :class="iconBtn" :disabled="i === session.papers.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(session, i, 1)"><Fa icon="chevron-down" class="text-xs" /></button>
                            </div>
                            <button v-if="canEdit" :class="iconBtn" class="hover:text-red-500!" :title="t('admin.presentations.unschedule')" @click="unschedule(session, i)"><Fa icon="xmark" class="text-xs" /></button>
                        </li>
                        <li v-if="!session.papers.length" key="empty" class="px-4 py-8 text-center text-sm text-neutral-400">{{ t('admin.presentations.emptySession') }}</li>
                    </TransitionGroup>
                </section>
            </TransitionGroup>
        </Card>

        <!-- Papers that can present but have no session yet -->
        <Card class="space-y-4">
            <h2 class="text-lg font-semibold"><Fa icon="hourglass-half" class="mr-2 text-gold-500" />{{ t('admin.presentations.waiting') }} ({{ formatNumber(waiting.length) }})</h2>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t(canEdit ? 'admin.presentations.waitingHint' : 'admin.viewOnly') }}</p>
            <TransitionGroup name="fade" tag="ul" class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                <li v-for="paper in waiting" :key="paper.code" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="font-medium">{{ tr(paper.title) }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400"><span class="font-mono">{{ paper.code }}</span> · {{ tr(paper.author) }}</p>
                    </div>
                    <select
                        v-if="canEdit"
                        class="rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm outline-none focus:border-gold-400 sm:w-80 dark:border-white/10 dark:bg-ink-800"
                        :aria-label="t('admin.presentations.addTo')"
                        @change="schedule(paper, $event.target.value)"
                    >
                        <option value="">{{ t('admin.presentations.addTo') }}</option>
                        <optgroup v-for="block in presentationBlocks" :key="block.id" :label="`${tr(block.label)} ${block.start}–${block.end}`">
                            <option v-for="session in sessionsIn(block)" :key="session.id" :value="session.id">{{ tr(roomOf(session.room).name) }} · {{ tr(trackOf(session.track).name) }}</option>
                        </optgroup>
                    </select>
                </li>
                <li v-if="!waiting.length" key="none" class="px-4 py-10 text-center text-sm text-neutral-500">{{ t('admin.presentations.allScheduled') }}</li>
            </TransitionGroup>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.presentations.mockNote') }}</p>
        </Card>
    </div>

    <!-- Add / edit session -->
    <Modal :show="!!editing" @close="editing = null">
        <form v-if="editing" class="space-y-4 p-6 sm:p-8" @submit.prevent="saveSession">
            <h2 class="text-xl font-semibold">{{ t(editing.id ? 'admin.presentations.editSession' : 'admin.presentations.addSession') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.block') }}</span>
                    <select v-model.number="editing.block" :class="field">
                        <option v-for="block in presentationBlocks" :key="block.id" :value="block.id">{{ tr(block.label) }} {{ block.start }}–{{ block.end }}</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.room') }}</span>
                    <select v-model.number="editing.room" :class="field">
                        <option v-for="room in presentationRooms" :key="room.id" :value="room.id">{{ tr(room.name) }} ({{ t('admin.presentations.seats', { n: room.capacity }) }})</option>
                    </select>
                </label>
            </div>
            <label class="block text-sm">
                <span class="mb-1.5 block font-medium">{{ t('submissions.fields.track') }}</span>
                <select v-model.number="editing.track" :class="field">
                    <option v-for="track in mockTracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                </select>
            </label>
            <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.chair') }}</span>
                    <input v-model="editing.chair" :class="field" :placeholder="t('admin.presentations.chairPlaceholder')">
                </label>
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.perPaper') }}</span>
                    <input v-model.number="editing.minutes" type="number" min="5" max="60" step="5" :class="[field, 'w-28']">
                </label>
            </div>
            <Transition name="fade">
                <p v-if="editorError" class="text-sm text-red-400"><Fa icon="circle-exclamation" class="mr-1" />{{ t(editorError) }}</p>
            </Transition>
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <button v-if="editing.id" type="button" class="flex items-center gap-2 text-sm text-red-300 hover:text-red-200" @click="removeSession">
                    <Fa icon="trash-can" /> {{ t('admin.presentations.removeSession') }}
                </button>
                <span v-else />
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="editing = null">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                        <Fa icon="floppy-disk" /> {{ t('common.save') }}
                    </button>
                </div>
            </div>
        </form>
    </Modal>
</template>
