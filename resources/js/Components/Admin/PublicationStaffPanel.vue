<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FileLink from '@/Components/Admin/FileLink.vue';
import { useI18n } from '@/i18n';

// Staff side after acceptance (conference track) or the journal's acceptance: camera-ready, slot and slides
const props = defineProps({
    submission: { type: Object, required: true },
    canSchedule: { type: Boolean, required: true },
});

const { t, formatDate } = useI18n();
const status = computed(() => props.submission.status);
const publication = computed(() => props.submission.publication);
const visible = computed(() => ['accepted', 'camera_ready', 'journal_accepted'].includes(status.value));

// Presentation slot (datetime-local works with 'Y-m-dTH:i' in local time)
const toLocalInput = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
const slotForm = useForm({ at: toLocalInput(publication.value.presentation?.at), room: publication.value.presentation?.room ?? '' });
watch(() => publication.value.presentation, (slot) => {
    slotForm.defaults({ at: toLocalInput(slot?.at), room: slot?.room ?? '' });
    slotForm.reset();
});
function saveSlot() {
    slotForm.transform((data) => ({ at: data.at || null, room: data.room || null }))
        .put(`/admin/submissions/${props.submission.id}/presentation`, { preserveScroll: true, preserveState: true });
}
function clearSlot() {
    slotForm.at = '';
    slotForm.room = '';
    saveSlot();
}

const input = 'rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800 dark:[color-scheme:dark]';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const box = 'rounded-lg border border-neutral-200 p-4 dark:border-white/10';
</script>

<template>
    <section v-if="visible" class="rounded-xl border border-gold-300 p-4 dark:border-gold-400/30">
        <h2 class="mb-4 flex items-center gap-2 font-semibold"><Fa icon="book" class="text-gold-400" /> {{ t('publication.title') }}</h2>

        <div class="space-y-4">
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <Fa :icon="status === 'journal_accepted' ? 'newspaper' : 'book'" class="text-gold-500" />
                <span class="font-medium">{{ t(`publication.route.${status === 'journal_accepted' ? 'journal' : 'conference'}`) }}</span>
            </p>

            <!-- Conference track: camera-ready file -->
            <div v-if="status !== 'journal_accepted'" :class="box">
                <p class="mb-2 text-sm font-medium">{{ t('publication.cameraReady.title') }}</p>
                <FileLink v-if="publication.cameraReady" :file="publication.cameraReady" icon="file-circle-check" />
                <p v-else class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('publication.staff.noCameraReady') }}</p>
            </div>

            <!-- Presentation slot (moves to the Presentations menu once that page reads real data) -->
            <form v-if="canSchedule" :class="box" class="space-y-3" @submit.prevent="saveSlot">
                <p class="flex items-center gap-2 text-sm font-medium"><Fa icon="person-chalkboard" class="text-gold-500" /> {{ t('publication.presentation.title') }}</p>
                <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                    <input v-model="slotForm.at" type="datetime-local" :class="[input, border(slotForm.errors.at)]" :aria-label="t('publication.presentation.when')">
                    <input v-model="slotForm.room" :placeholder="t('publication.presentation.roomPlaceholder')" :class="[input, border(slotForm.errors.room)]" maxlength="255">
                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                        :disabled="!slotForm.isDirty || slotForm.processing"
                    >
                        <Fa icon="floppy-disk" /> {{ t('common.save') }}
                    </button>
                </div>
                <span v-if="slotForm.errors.at || slotForm.errors.room" class="block text-xs text-red-500 dark:text-red-400">{{ t('publication.presentation.bothRequired') }}</span>
                <button v-if="publication.presentation" type="button" class="text-xs text-neutral-500 hover:text-red-500 dark:text-neutral-400" @click="clearSlot">
                    <Fa icon="xmark" /> {{ t('publication.presentation.clear') }}
                </button>
            </form>

            <!-- Slides the presenter uploaded -->
            <div v-if="publication.presentation" :class="box">
                <p class="mb-2 text-sm font-medium">{{ t('publication.slides.title') }}</p>
                <FileLink v-if="publication.slides" :file="publication.slides" icon="file-powerpoint" />
                <p v-else class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ t('publication.slides.none') }}
                    <template v-if="publication.slidesUntil"> · {{ t('publication.slides.until', { date: formatDate(publication.slidesUntil, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }) }}</template>
                </p>
            </div>
        </div>
    </section>
</template>
