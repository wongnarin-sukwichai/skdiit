<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { formatFileSize } from '@/utils/format';
import { mockTracks, publicationQueue } from '@/data/mock';

/*
 * Publication (admin, editor): every paper of this year that will present, by step - camera-ready files
 * (conference track), papers the journal accepted, and presentation slots with the slides.
 * Mock phase: the same work is already real on each paper's page (Submissions › detail).
 */
const { t, tr, formatDate, formatNumber } = useI18n();
const { toast } = useToast();

const list = reactive(publicationQueue.map((p) => ({ ...p })));

const tabs = [
    { key: 'cameraReady', icon: 'book', match: (p) => p.route === 'conference' },
    { key: 'journal', icon: 'newspaper', match: (p) => p.route === 'journal' },
    { key: 'presentation', icon: 'person-chalkboard', match: () => true },
];
const tab = ref('cameraReady');

const filters = reactive({ q: '', track: '' });
const normalize = (text) => String(text ?? '').toLowerCase();
const rows = computed(() => {
    const current = tabs.find((x) => x.key === tab.value);
    const q = normalize(filters.q.trim());
    return list.filter((p) => current.match(p)
        && (!filters.track || p.track.id === Number(filters.track))
        && (!q || normalize(`${p.code} ${p.title.en} ${p.title.th} ${p.author.en} ${p.author.th} ${p.email}`).includes(q)));
});
const countOf = (key) => list.filter(tabs.find((x) => x.key === key).match).length;

const stats = computed(() => ({
    waitingCameraReady: list.filter((p) => p.status === 'accepted').length,
    journalAccepted: list.filter((p) => p.status === 'journal_accepted').length,
    noSlot: list.filter((p) => !p.presentation).length,
    noSlides: list.filter((p) => p.presentation && !p.slides).length,
}));
const statCards = [
    { key: 'waitingCameraReady', icon: 'file-arrow-up' },
    { key: 'journalAccepted', icon: 'newspaper' },
    { key: 'noSlot', icon: 'person-chalkboard' },
    { key: 'noSlides', icon: 'file-powerpoint' },
];

const when = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

// Real system: an email reminder to the author (camera-ready file or slides)
function remind(paper) {
    toast('admin.publication.toast.reminded', { email: paper.email });
}

// ---- Presentation slot ----
const scheduling = ref(null);
const slot = reactive({ at: '', room: '' });
watch(scheduling, (paper) => paper && Object.assign(slot, { at: paper.presentation?.at ?? '', room: paper.presentation?.room ?? '' }));
function saveSlot() {
    scheduling.value.presentation = slot.at && slot.room ? { at: slot.at, room: slot.room } : null;
    toast('publication.scheduleSaved');
    scheduling.value = null;
}

const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 py-2.5 pr-3 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const smallBtn = 'inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium whitespace-nowrap transition hover:border-gold-400 hover:text-gold-600 dark:border-white/15 dark:hover:text-gold-400';
const th = 'px-3 py-3 font-semibold';
const td = 'px-3 py-2.5';
const muted = 'text-xs text-neutral-500 dark:text-neutral-400';
</script>

<template>
    <Head :title="t('admin.menu.publication')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="book" :title="t('admin.menu.publication')" :subtitle="t('admin.publication.subtitle')" />
            <StatCards :stats="stats" :cards="statCards" />
        </Card>

        <Card class="space-y-5">
            <nav class="flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-white/10" role="tablist">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    role="tab"
                    :aria-selected="tab === item.key"
                    :class="tab === item.key
                        ? 'border-gold-400 text-gold-600 dark:text-gold-400'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                    class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                    @click="tab = item.key"
                >
                    <Fa :icon="item.icon" /> {{ t(`admin.publication.tabs.${item.key}`) }}
                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs dark:bg-white/10">{{ formatNumber(countOf(item.key)) }}</span>
                </button>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
                <label class="relative min-w-60 flex-1">
                    <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                    <input v-model="filters.q" :placeholder="t('admin.publication.search')" :class="[input, 'pl-11']">
                </label>
                <select v-model="filters.track" :class="[input, 'w-auto pl-3']" :aria-label="t('submissions.fields.track')">
                    <option value="">{{ t('admin.accounts.allTracks') }}</option>
                    <option v-for="track in mockTracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                </select>
            </div>

            <p class="text-sm text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t(`admin.publication.hint.${tab}`) }}</p>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <Transition name="fade" mode="out-in">
                    <table :key="tab" class="w-full min-w-250 text-sm">
                        <thead class="bg-gold-300 text-left text-black dark:bg-gold-400">
                            <tr>
                                <th :class="th">{{ t('admin.publication.paper') }}</th>
                                <th :class="th">{{ t('submissions.fields.track') }}</th>
                                <template v-if="tab === 'cameraReady'">
                                    <th :class="th">{{ t('admin.publication.acceptedAt') }}</th>
                                    <th :class="th">{{ t('publication.cameraReady.title') }}</th>
                                </template>
                                <template v-else-if="tab === 'journal'">
                                    <th :class="th">{{ t('admin.publication.journalAcceptedAt') }}</th>
                                    <th :class="[th, 'text-center']">{{ t('registrants.status') }}</th>
                                </template>
                                <template v-else>
                                    <th :class="th">{{ t('admin.publication.slot') }}</th>
                                    <th :class="th">{{ t('publication.slides.title') }}</th>
                                </template>
                                <th :class="[th, 'text-right']">{{ t('registrants.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                            <tr v-for="paper in rows" :key="paper.id" class="transition odd:bg-neutral-50 hover:bg-gold-50 dark:odd:bg-white/2 dark:hover:bg-gold-400/5">
                                <td :class="[td, 'max-w-md']">
                                    <p class="font-mono text-xs text-gold-600 dark:text-gold-400">{{ paper.code }}</p>
                                    <p class="line-clamp-2 font-medium">{{ tr(paper.title) }}</p>
                                    <p :class="muted">{{ tr(paper.author) }} · {{ paper.email }}</p>
                                </td>
                                <td :class="[td, 'max-w-48 text-neutral-600 dark:text-neutral-300']"><span class="line-clamp-2">{{ tr(paper.track.name) }}</span></td>

                                <!-- Conference track: camera-ready file -->
                                <template v-if="tab === 'cameraReady'">
                                    <td :class="[td, 'whitespace-nowrap']">{{ formatDate(paper.acceptedAt) }}</td>
                                    <td :class="td">
                                        <template v-if="paper.cameraReady">
                                            <p class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-300"><Fa icon="circle-check" /> {{ t('admin.publication.received') }}</p>
                                            <p :class="muted">{{ paper.cameraReady.fileName }} · {{ formatFileSize(paper.cameraReady.size) }} · {{ formatDate(paper.cameraReady.at) }}</p>
                                        </template>
                                        <p v-else class="flex items-center gap-1.5 text-amber-700 dark:text-amber-300"><Fa icon="clock" /> {{ t('admin.publication.notReceived') }}</p>
                                    </td>
                                    <td :class="[td, 'text-right']">
                                        <button v-if="paper.cameraReady" :class="smallBtn" @click="toast('admin.publication.toast.download')"><Fa icon="download" /> {{ t('admin.publication.download') }}</button>
                                        <button v-else :class="smallBtn" @click="remind(paper)"><Fa icon="paper-plane" /> {{ t('admin.publication.remind') }}</button>
                                    </td>
                                </template>

                                <!-- Accepted by the journal (no camera-ready here) -->
                                <template v-else-if="tab === 'journal'">
                                    <td :class="[td, 'whitespace-nowrap']">{{ formatDate(paper.acceptedAt) }}</td>
                                    <td :class="[td, 'text-center']"><SubmissionStatusBadge :status="paper.status" /></td>
                                    <td :class="td" />
                                </template>

                                <!-- Presentation slot and slides -->
                                <template v-else>
                                    <td :class="td">
                                        <template v-if="paper.presentation">
                                            <p class="font-medium">{{ when(paper.presentation.at) }}</p>
                                            <p :class="muted">{{ paper.presentation.room }}</p>
                                        </template>
                                        <span v-else class="text-neutral-400">{{ t('admin.publication.noSlot') }}</span>
                                    </td>
                                    <td :class="td">
                                        <template v-if="paper.slides">
                                            <p class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-300"><Fa icon="circle-check" /> {{ t('admin.publication.received') }}</p>
                                            <p :class="muted">{{ paper.slides.fileName }} · {{ formatFileSize(paper.slides.size) }}</p>
                                        </template>
                                        <span v-else class="text-neutral-400">{{ t(paper.presentation ? 'admin.publication.notReceived' : 'admin.publication.slidesAfterSlot') }}</span>
                                    </td>
                                    <td :class="[td, 'text-right']">
                                        <div class="flex justify-end gap-2">
                                            <button v-if="paper.presentation && !paper.slides" :class="smallBtn" @click="remind(paper)"><Fa icon="paper-plane" /> {{ t('admin.publication.remind') }}</button>
                                            <button :class="smallBtn" @click="scheduling = paper"><Fa icon="calendar-days" /> {{ t(paper.presentation ? 'admin.publication.changeSlot' : 'admin.publication.setSlot') }}</button>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="5" class="px-4 py-12 text-center text-neutral-500">{{ t('admin.publication.empty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </Transition>
            </div>

            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                <Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.publication.mockNote') }}
            </p>
        </Card>
    </div>

    <!-- Presentation slot -->
    <Modal :show="!!scheduling" @close="scheduling = null">
        <form v-if="scheduling" class="space-y-4 p-6 sm:p-8" @submit.prevent="saveSlot">
            <div>
                <h2 class="mb-1 text-xl font-semibold">{{ t('admin.publication.slot') }}</h2>
                <p class="text-sm text-neutral-300"><span class="font-mono text-xs text-gold-400">{{ scheduling.code }}</span> {{ tr(scheduling.title) }}</p>
            </div>
            <label class="block text-sm">
                <span class="mb-1.5 block font-medium">{{ t('admin.publication.slotAt') }}</span>
                <input v-model="slot.at" type="datetime-local" class="w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 outline-none scheme-dark focus:border-gold-400">
            </label>
            <label class="block text-sm">
                <span class="mb-1.5 block font-medium">{{ t('admin.publication.slotRoom') }}</span>
                <input v-model="slot.room" class="w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 outline-none focus:border-gold-400">
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="scheduling = null">{{ t('common.cancel') }}</button>
                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
