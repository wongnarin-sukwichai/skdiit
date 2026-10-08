<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { formatFileSize } from '@/utils/format';

// Staff side after acceptance: the author's option, the camera-ready file, the journal result and the presentation slot
const props = defineProps({
    submission: { type: Object, required: true },
    canSchedule: { type: Boolean, required: true },
});

const { t, formatDate } = useI18n();
const status = computed(() => props.submission.status);
const publication = computed(() => props.submission.publication);
const visible = computed(() => ['accepted', 'option_a', 'camera_ready', 'option_b', 'journal_accepted'].includes(status.value));

// Option B: the journal's answer
const journalForm = useForm({ journal: publication.value.journal ?? '', result: '', comment: '' });
const confirmOpen = ref(false);
function saveJournal() {
    journalForm.post(`/admin/submissions/${props.submission.id}/journal-result`, {
        preserveScroll: true,
        onSuccess: () => journalForm.reset('result', 'comment'),
        onFinish: () => (confirmOpen.value = false),
    });
}

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

        <p v-if="status === 'accepted'" class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('publication.staff.waitingChoice') }}</p>

        <div v-else class="space-y-4">
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <Fa :icon="publication.option === 'a' ? 'book' : 'newspaper'" class="text-gold-500" />
                <span class="font-medium">{{ t(`publication.option.${publication.option}.title`) }}</span>
                <span v-if="publication.journal" class="text-neutral-500 dark:text-neutral-400">· {{ t('publication.journal.name') }}: {{ publication.journal }}</span>
            </p>

            <!-- Option A: camera-ready file -->
            <div v-if="['option_a', 'camera_ready'].includes(status)" :class="box">
                <p class="mb-2 text-sm font-medium">{{ t('publication.cameraReady.title') }}</p>
                <a v-if="publication.cameraReady" :href="publication.cameraReady.url" class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 text-sm transition hover:border-gold-400 dark:border-white/10">
                    <Fa icon="file-circle-check" class="text-2xl text-emerald-500" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ publication.cameraReady.name }}</span>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ formatFileSize(publication.cameraReady.size) }} · {{ formatDate(publication.cameraReady.at, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                        </span>
                    </span>
                    <Fa icon="download" class="text-neutral-400" />
                </a>
                <p v-else class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('publication.staff.noCameraReady') }}</p>
            </div>

            <!-- Option B: record the journal's answer -->
            <form v-if="status === 'option_b'" :class="box" class="space-y-3" @submit.prevent="confirmOpen = true">
                <p class="text-sm font-medium">{{ t('publication.journal.record') }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('publication.journal.recordHint') }}</p>
                <label class="block">
                    <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('publication.journal.name') }} <span class="text-red-500">*</span></span>
                    <input v-model="journalForm.journal" :class="[input, border(journalForm.errors.journal), 'w-full']" maxlength="255">
                    <span v-if="journalForm.errors.journal" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ t(journalForm.errors.journal) }}</span>
                </label>
                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup">
                    <button
                        v-for="result in ['accepted', 'rejected']"
                        :key="result"
                        type="button"
                        role="radio"
                        :aria-checked="journalForm.result === result"
                        :class="journalForm.result === result
                            ? (result === 'accepted' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'border-red-500 bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300')
                            : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                        class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                        @click="journalForm.result = result"
                    >
                        <Fa :icon="result === 'accepted' ? 'circle-check' : 'xmark'" /> {{ t(`publication.journal.${result}Option`) }}
                    </button>
                </div>
                <textarea v-model="journalForm.comment" rows="3" maxlength="5000" :placeholder="t('publication.journal.commentPlaceholder')" :class="[input, border(journalForm.errors.comment), 'w-full']" />
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                    :disabled="!journalForm.journal.trim() || !journalForm.result || journalForm.processing"
                >
                    <Fa icon="floppy-disk" /> {{ t('submissions.screening.save') }}
                </button>
            </form>

            <!-- Presentation slot -->
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
        </div>

        <Modal :show="confirmOpen" @close="confirmOpen = false">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="newspaper" />
                </span>
                <h2 class="text-xl font-semibold">{{ t('publication.journal.confirmTitle') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ journalForm.result === 'rejected' ? t('publication.journal.confirmRejected', { code: submission.code }) : t('publication.journal.confirmAccepted', { code: submission.code, journal: journalForm.journal }) }}
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirmOpen = false">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="journalForm.processing" @click="saveJournal">
                        <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
