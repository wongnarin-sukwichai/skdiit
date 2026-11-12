<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

/*
 * Presentation schedule (2026-11-12, real data: PresentationController). Admin keeps the rooms and the sessions
 * (a day, a time span, a room, a track, a chair typed in, minutes per paper) and puts papers in running order;
 * editors see it and put a paper in a session from the paper page. Each paper's time follows from its place.
 */
const props = defineProps({
    sessions: { type: Array, required: true }, // PresentationSession::toClient(withPapers)
    rooms: { type: Array, required: true }, // with `sessions` (how many use the room)
    tracks: { type: Array, required: true },
    waiting: { type: Array, required: true }, // can present, no session yet
    days: { type: Array, required: true }, // presentation days (Y-m-d), kept on this page
    conferenceDays: { type: Array, default: () => [] }, // Settings › Conference: date..end date
    canEdit: { type: Boolean, required: true },
});

const { t, tr, formatDate, formatNumber } = useI18n();

const stats = computed(() => ({
    sessions: props.sessions.length,
    scheduled: props.sessions.reduce((n, s) => n + s.papers.length, 0),
    unscheduled: props.waiting.length,
    noSlides: props.sessions.reduce((n, s) => n + s.papers.filter((p) => !p.slides).length, 0),
}));
const statCards = [
    { key: 'sessions', icon: 'person-chalkboard' },
    { key: 'scheduled', icon: 'calendar-days' },
    { key: 'unscheduled', icon: 'hourglass-half' },
    { key: 'noSlides', icon: 'file-powerpoint' },
];

// Sessions grouped by day, then by time span
const byDay = computed(() => {
    const days = [...new Set([...props.days, ...props.sessions.map((s) => s.date)])].sort();
    return days.map((date) => {
        const ofDay = props.sessions.filter((s) => s.date === date);
        const spans = [...new Set(ofDay.map((s) => `${s.start}–${s.end}`))].sort();
        return { date, spans: spans.map((span) => ({ span, sessions: ofDay.filter((s) => `${s.start}–${s.end}` === span) })) };
    });
});
const dayLabel = (date) => formatDate(date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const sessionLabel = (s) => `${formatDate(s.date, { day: 'numeric', month: 'short' })} ${s.start}–${s.end} · ${tr(s.room.name)}${s.track ? ` · ${tr(s.track.name)}` : ''}`;
const full = (s) => s.papers.length >= s.capacity;

const options = { preserveScroll: true, preserveState: true };

// ---- Presentation days (a keynote day may have no session) ----
const newDay = ref('');
function addDay() {
    if (!newDay.value) return;
    router.post('/admin/presentations/days', { date: newDay.value }, { ...options, onSuccess: () => { newDay.value = ''; } });
}
function removeDay(date) {
    router.delete(`/admin/presentations/days/${date}`, options);
}
const outsideConference = (date) => props.conferenceDays.length > 0 && !props.conferenceDays.includes(date);
const sessionsOn = (date) => props.sessions.filter((s) => s.date === date).length;

// ---- Papers ----
function assign(paper, sessionId) {
    if (sessionId === '') return;
    router.put(`/admin/submissions/${paper.id}/presentation`, { session_id: sessionId ? Number(sessionId) : null }, options);
}
function move(paper, direction) {
    router.put(`/admin/presentations/papers/${paper.id}/move`, { direction }, options);
}

// ---- Sessions ----
const editing = ref(null); // session id, or 'new'
const sessionForm = useForm({ date: '', start_time: '09:00', end_time: '10:30', presentation_room_id: '', track_id: '', chair: '', minutes: 15 });
function openSession(session = null) {
    sessionForm.clearErrors();
    editing.value = session ? session.id : 'new';
    Object.assign(sessionForm, session
        ? { date: session.date, start_time: session.start, end_time: session.end, presentation_room_id: session.room.id, track_id: session.track?.id ?? '', chair: session.chair ?? '', minutes: session.minutes }
        : { date: props.days[0] ?? '', start_time: '09:00', end_time: '10:30', presentation_room_id: props.rooms[0]?.id ?? '', track_id: '', chair: '', minutes: 15 });
}
function saveSession() {
    const done = { ...options, onSuccess: () => { editing.value = null; } };
    sessionForm.transform((data) => ({ ...data, track_id: data.track_id || null, chair: data.chair || null }));
    if (editing.value === 'new') sessionForm.post('/admin/presentations/sessions', done);
    else sessionForm.put(`/admin/presentations/sessions/${editing.value}`, done);
}
const removing = ref(false);
function removeSession() {
    router.delete(`/admin/presentations/sessions/${editing.value}`, { ...options, onSuccess: () => { removing.value = false; editing.value = null; } });
}

// ---- Rooms ----
const roomsOpen = ref(false);
const roomForm = useForm({ id: null, name_th: '', name_en: '', capacity: '' });
function editRoom(room = null) {
    roomForm.clearErrors();
    Object.assign(roomForm, room ? { id: room.id, name_th: room.name_th, name_en: room.name_en, capacity: room.capacity ?? '' } : { id: null, name_th: '', name_en: '', capacity: '' });
}
function saveRoom() {
    roomForm.transform((data) => ({ name_th: data.name_th, name_en: data.name_en, capacity: data.capacity || null }));
    const done = { ...options, onSuccess: () => editRoom() };
    if (roomForm.id) roomForm.put(`/admin/presentations/rooms/${roomForm.id}`, done);
    else roomForm.post('/admin/presentations/rooms', done);
}
function deleteRoom(room) {
    router.delete(`/admin/presentations/rooms/${room.id}`, options);
}

const iconBtn = 'grid size-7 place-items-center rounded-md text-neutral-400 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-25 disabled:hover:bg-transparent dark:hover:bg-white/10';
const field = 'w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400';
const err = (form, key) => (form.errors[key] ? t(form.errors[key]) : null);
</script>

<template>
    <Head :title="t('admin.menu.presentations')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="person-chalkboard" :title="t('admin.menu.presentations')" :subtitle="t('admin.presentations.subtitle')" />
            <StatCards :stats="stats" :cards="statCards" />
            <!-- Presentation days: added and removed here (default: the conference days) -->
            <div class="space-y-2">
                <p class="text-sm font-medium"><Fa icon="calendar-days" class="mr-1.5 text-gold-500" />{{ t('admin.presentations.days') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="day in days"
                        :key="day"
                        :class="outsideConference(day) ? 'border-amber-400 bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300' : 'border-neutral-300 dark:border-white/15'"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm"
                        :title="outsideConference(day) ? t('admin.presentations.outsideConference') : undefined"
                    >
                        <Fa v-if="outsideConference(day)" icon="triangle-exclamation" class="text-xs" />
                        {{ formatDate(day, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">· {{ t('admin.presentations.sessionsOn', { n: sessionsOn(day) }) }}</span>
                        <button v-if="canEdit" type="button" class="text-neutral-400 hover:text-red-500 disabled:opacity-30 disabled:hover:text-neutral-400" :disabled="sessionsOn(day) > 0" :title="sessionsOn(day) ? t('admin.presentations.dayInUse') : t('admin.presentations.removeDay')" @click="removeDay(day)"><Fa icon="xmark" class="text-xs" /></button>
                    </span>
                    <form v-if="canEdit" class="inline-flex items-center gap-2" @submit.prevent="addDay">
                        <input v-model="newDay" type="date" class="rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-1.5 text-sm outline-none focus:border-gold-400 dark:border-white/10 dark:bg-ink-800 dark:scheme-dark" :aria-label="t('admin.presentations.addDay')">
                        <button type="submit" class="flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-sm transition hover:border-gold-400 disabled:opacity-40 dark:border-white/20" :disabled="!newDay"><Fa icon="plus" class="text-xs" /> {{ t('admin.presentations.addDay') }}</button>
                    </form>
                </div>
                <p v-if="!days.length" class="text-sm text-amber-700 dark:text-amber-300"><Fa icon="triangle-exclamation" class="mr-1" />{{ t('admin.presentations.noDays') }}</p>
                <p v-else class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.presentations.daysHint') }}</p>
            </div>
            <div v-if="canEdit" class="flex flex-wrap justify-end gap-2">
                <button type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20" @click="roomsOpen = true; editRoom()">
                    <Fa icon="location-dot" /> {{ t('admin.presentations.manageRooms') }} ({{ formatNumber(rooms.length) }})
                </button>
                <!-- No room yet: open the rooms dialog first (a session needs a room) -->
                <button type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="rooms.length ? openSession() : (roomsOpen = true, editRoom())">
                    <Fa icon="plus" /> {{ t('admin.presentations.addSession') }}
                </button>
            </div>
        </Card>

        <!-- One card per conference day; sessions by time span, side by side by room -->
        <Card v-for="day in byDay" :key="day.date" class="space-y-5">
            <h2 class="flex flex-wrap items-center gap-2 text-lg font-semibold">
                <span><Fa icon="calendar-days" class="mr-2 text-gold-500" />{{ dayLabel(day.date) }}</span>
                <span v-if="!days.includes(day.date)" class="rounded-full border border-red-300 bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300"><Fa icon="triangle-exclamation" class="mr-1" />{{ t('admin.presentations.notADay') }}</span>
            </h2>
            <p v-if="!day.spans.length" class="rounded-xl border border-dashed border-neutral-300 py-8 text-center text-sm text-neutral-500 dark:border-white/15 dark:text-neutral-400">{{ t('admin.presentations.noSessions') }}</p>
            <div v-for="group in day.spans" :key="group.span" class="space-y-3">
                <p class="text-sm font-medium text-neutral-600 dark:text-neutral-300"><Fa icon="clock" class="mr-1.5 text-gold-500" />{{ group.span }}</p>
                <TransitionGroup name="fade" tag="div" class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-4">
                    <section v-for="session in group.sessions" :key="session.id" class="flex flex-col rounded-xl border border-neutral-200 dark:border-white/10">
                        <header class="space-y-1 border-b border-neutral-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold"><Fa icon="location-dot" class="mr-1.5 text-gold-500" />{{ tr(session.room.name) }}</p>
                                <button v-if="canEdit" :class="iconBtn" :title="t('admin.presentations.editSession')" @click="openSession(session)"><Fa icon="pen" class="text-xs" /></button>
                            </div>
                            <p class="text-sm text-gold-700 dark:text-gold-300">{{ session.track ? tr(session.track.name) : t('admin.presentations.anyTrack') }}</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.presentations.chair') }}: {{ session.chair || '—' }}</p>
                            <div class="pt-1">
                                <div class="h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10">
                                    <div :class="full(session) ? 'bg-emerald-500' : 'bg-gold-400'" class="h-full rounded-full transition-all" :style="{ width: `${Math.min(100, (session.papers.length / Math.max(1, session.capacity)) * 100)}%` }" />
                                </div>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.presentations.places', { n: session.papers.length, max: session.capacity, each: session.minutes }) }}</p>
                            </div>
                        </header>
                        <TransitionGroup name="fade" tag="ol" class="flex-1 divide-y divide-neutral-100 dark:divide-white/5">
                            <li v-for="(paper, i) in session.papers" :key="paper.id" class="flex gap-3 px-4 py-2.5 text-sm">
                                <span class="w-11 shrink-0 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ paper.at }}</span>
                                <div class="min-w-0 flex-1">
                                    <Link :href="`/admin/submissions/${paper.id}`" class="line-clamp-2 hover:text-gold-600 dark:hover:text-gold-400">{{ tr(paper.title) }}</Link>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        <span class="font-mono">{{ paper.code }}</span> · {{ paper.authors }}
                                        <span v-if="!paper.slides" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"><Fa icon="file-powerpoint" class="mr-1" />{{ t('admin.stats.noSlides') }}</span>
                                    </p>
                                </div>
                                <div v-if="canEdit" class="flex shrink-0 flex-col">
                                    <button :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(paper, 'up')"><Fa icon="chevron-up" class="text-xs" /></button>
                                    <button :class="iconBtn" :disabled="i === session.papers.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(paper, 'down')"><Fa icon="chevron-down" class="text-xs" /></button>
                                </div>
                                <button v-if="canEdit" :class="iconBtn" class="hover:text-red-500!" :title="t('admin.presentations.unschedule')" @click="assign(paper, null)"><Fa icon="xmark" class="text-xs" /></button>
                            </li>
                            <li v-if="!session.papers.length" key="empty" class="px-4 py-8 text-center text-sm text-neutral-400">{{ t('admin.presentations.emptySession') }}</li>
                        </TransitionGroup>
                    </section>
                </TransitionGroup>
            </div>
        </Card>

        <!-- Papers that can present but have no session yet -->
        <Card class="space-y-4">
            <h2 class="text-lg font-semibold"><Fa icon="hourglass-half" class="mr-2 text-gold-500" />{{ t('admin.presentations.waiting') }} ({{ formatNumber(waiting.length) }})</h2>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t(canEdit ? 'admin.presentations.waitingHint' : 'admin.presentations.waitingHintView') }}</p>
            <TransitionGroup name="fade" tag="ul" class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                <li v-for="paper in waiting" :key="paper.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1 text-sm">
                        <Link :href="`/admin/submissions/${paper.id}`" class="font-medium hover:text-gold-600 dark:hover:text-gold-400">{{ tr(paper.title) }}</Link>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            <span class="font-mono">{{ paper.code }}</span> · {{ paper.authors }}
                            <template v-if="paper.track"> · {{ tr(paper.track.name) }}</template>
                            <span v-if="paper.journal" class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><Fa icon="newspaper" class="mr-1" />{{ t('admin.presentations.fromJournal') }}</span>
                        </p>
                    </div>
                    <select
                        v-if="canEdit"
                        class="rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm outline-none focus:border-gold-400 sm:w-96 dark:border-white/10 dark:bg-ink-800"
                        :aria-label="t('admin.presentations.addTo')"
                        @change="assign(paper, $event.target.value)"
                    >
                        <option value="">{{ t('admin.presentations.addTo') }}</option>
                        <option v-for="session in sessions" :key="session.id" :value="session.id" :disabled="full(session)">
                            {{ sessionLabel(session) }} ({{ full(session) ? t('admin.presentations.fullShort') : t('admin.presentations.left', { n: session.capacity - session.papers.length }) }})
                        </option>
                    </select>
                </li>
                <li v-if="!waiting.length" key="none" class="px-4 py-10 text-center text-sm text-neutral-500">{{ t('admin.presentations.allScheduled') }}</li>
            </TransitionGroup>
        </Card>
    </div>

    <!-- Add / edit session -->
    <Modal :show="!!editing" @close="editing = null">
        <form v-if="editing" class="space-y-4 p-6 sm:p-8" @submit.prevent="saveSession">
            <h2 class="text-xl font-semibold">{{ t(editing === 'new' ? 'admin.presentations.addSession' : 'admin.presentations.editSession') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm sm:col-span-3">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.day') }}</span>
                    <select v-if="days.length" v-model="sessionForm.date" :class="field" required>
                        <option v-for="day in days" :key="day" :value="day">{{ dayLabel(day) }}</option>
                    </select>
                    <input v-else v-model="sessionForm.date" type="date" :class="[field, 'scheme-dark']" required>
                    <span v-if="err(sessionForm, 'date')" class="mt-1 block text-xs text-red-400">{{ err(sessionForm, 'date') }}</span>
                </label>
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.start') }}</span>
                    <input v-model="sessionForm.start_time" type="time" :class="[field, 'scheme-dark']" required>
                </label>
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.end') }}</span>
                    <input v-model="sessionForm.end_time" type="time" :class="[field, 'scheme-dark']" required>
                    <span v-if="err(sessionForm, 'end_time')" class="mt-1 block text-xs text-red-400">{{ err(sessionForm, 'end_time') }}</span>
                </label>
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('admin.presentations.perPaper') }}</span>
                    <input v-model.number="sessionForm.minutes" type="number" min="5" max="120" step="5" :class="field" required>
                </label>
            </div>
            <label class="block text-sm">
                <span class="mb-1.5 block font-medium">{{ t('admin.presentations.room') }}</span>
                <select v-model.number="sessionForm.presentation_room_id" :class="field" required>
                    <option v-for="room in rooms" :key="room.id" :value="room.id">{{ tr(room.name) }}<template v-if="room.capacity"> ({{ t('admin.presentations.seats', { n: room.capacity }) }})</template></option>
                </select>
                <span v-if="err(sessionForm, 'presentation_room_id')" class="mt-1 block text-xs text-red-400">{{ err(sessionForm, 'presentation_room_id') }}</span>
            </label>
            <label class="block text-sm">
                <span class="mb-1.5 block font-medium">{{ t('submissions.fields.track') }}</span>
                <select v-model="sessionForm.track_id" :class="field">
                    <option value="">{{ t('admin.presentations.anyTrack') }}</option>
                    <option v-for="track in tracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1.5 block font-medium">{{ t('admin.presentations.chair') }}</span>
                <input v-model="sessionForm.chair" :class="field" maxlength="255" :placeholder="t('admin.presentations.chairPlaceholder')">
            </label>
            <p class="text-xs text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-400" />{{ t('admin.presentations.timesFollow') }}</p>
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <button v-if="editing !== 'new'" type="button" class="flex items-center gap-2 text-sm text-red-300 hover:text-red-200" @click="removing = true">
                    <Fa icon="trash-can" /> {{ t('admin.presentations.removeSession') }}
                </button>
                <span v-else />
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="editing = null">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="sessionForm.processing">
                        <Fa icon="floppy-disk" /> {{ t('common.save') }}
                    </button>
                </div>
            </div>
            <Transition name="fade">
                <p v-if="removing" class="flex flex-wrap items-center gap-3 rounded-lg border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-200">
                    {{ t('admin.presentations.removeConfirm') }}
                    <button type="button" class="rounded-md bg-red-500 px-3 py-1.5 font-semibold text-white" @click="removeSession">{{ t('admin.presentations.removeSession') }}</button>
                    <button type="button" class="underline" @click="removing = false">{{ t('common.cancel') }}</button>
                </p>
            </Transition>
        </form>
    </Modal>

    <!-- Rooms -->
    <Modal :show="roomsOpen" max-width="max-w-xl" @close="roomsOpen = false">
        <div class="space-y-5 p-6 sm:p-8">
            <h2 class="text-xl font-semibold"><Fa icon="location-dot" class="mr-2 text-gold-400" />{{ t('admin.presentations.manageRooms') }}</h2>
            <p v-if="!rooms.length" class="rounded-lg border border-amber-400/30 bg-amber-400/10 p-3 text-sm text-amber-200"><Fa icon="circle-info" class="mr-1" />{{ t('admin.presentations.needRoomFirst') }}</p>
            <ul class="divide-y divide-white/10 rounded-lg border border-white/10">
                <li v-for="room in rooms" :key="room.id" class="flex items-center gap-3 px-3 py-2.5 text-sm">
                    <div class="min-w-0 flex-1">
                        <p>{{ room.name_th }} <span class="text-neutral-400">/ {{ room.name_en }}</span></p>
                        <p class="text-xs text-neutral-400">
                            <template v-if="room.capacity">{{ t('admin.presentations.seats', { n: room.capacity }) }} · </template>{{ t('admin.presentations.usedBy', { n: room.sessions }) }}
                        </p>
                    </div>
                    <button type="button" :class="iconBtn" :title="t('common.edit')" @click="editRoom(room)"><Fa icon="pen" class="text-xs" /></button>
                    <button type="button" :class="iconBtn" class="hover:text-red-400!" :disabled="room.sessions > 0" :title="room.sessions ? t('admin.presentations.roomInUse') : t('common.delete')" @click="deleteRoom(room)"><Fa icon="trash-can" class="text-xs" /></button>
                </li>
                <li v-if="!rooms.length" class="px-3 py-6 text-center text-sm text-neutral-400">{{ t('admin.presentations.noRooms') }}</li>
            </ul>
            <form class="grid gap-3 sm:grid-cols-[1fr_1fr_6rem]" @submit.prevent="saveRoom">
                <input v-model="roomForm.name_th" :class="field" :placeholder="t('admin.presentations.roomTh')" required maxlength="255">
                <input v-model="roomForm.name_en" :class="field" :placeholder="t('admin.presentations.roomEn')" required maxlength="255">
                <input v-model.number="roomForm.capacity" type="number" min="1" :class="field" :placeholder="t('admin.presentations.capacity')">
                <div class="flex justify-end gap-2 sm:col-span-3">
                    <button v-if="roomForm.id" type="button" class="rounded-lg border border-white/20 px-4 py-2 text-sm" @click="editRoom()">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="roomForm.processing">
                        <Fa :icon="roomForm.id ? 'floppy-disk' : 'plus'" /> {{ t(roomForm.id ? 'common.save' : 'admin.presentations.addRoom') }}
                    </button>
                </div>
            </form>
            <div class="flex justify-end">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="roomsOpen = false">{{ t('common.close') }}</button>
            </div>
        </div>
    </Modal>
</template>
