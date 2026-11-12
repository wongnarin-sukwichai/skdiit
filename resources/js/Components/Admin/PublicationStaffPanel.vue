<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import FileLink from '@/Components/Admin/FileLink.vue';
import FilePick from '@/Components/Admin/FilePick.vue';
import Modal from '@/Components/Modal.vue';
import { ATTACHMENT_TYPES as attachmentTypes } from '@/utils/editorAttachment';
import { useI18n } from '@/i18n';

// Staff side after acceptance (conference track) or the journal's acceptance: camera-ready, slot and slides
const props = defineProps({
    submission: { type: Object, required: true },
    canSchedule: { type: Boolean, required: true },
    maxMb: { type: Number, default: 10 }, // upload limit, for the file sent with "fix"
    sessionOptions: { type: Array, default: () => [] }, // presentation sessions to pick from (the paper's track first)
});

const { t, tr, formatDate } = useI18n();
const status = computed(() => props.submission.status);
const publication = computed(() => props.submission.publication);
const visible = computed(() => ['accepted', 'camera_ready', 'journal_accepted'].includes(status.value));

// Presentation (2026-11-12): pick a session made on the Presentations page; the time follows from the running order
const slotForm = useForm({ session_id: publication.value.presentation?.session ?? '' });
watch(() => publication.value.presentation, (slot) => {
    slotForm.defaults({ session_id: slot?.session ?? '' });
    slotForm.reset();
});
function saveSlot() {
    slotForm.transform((data) => ({ session_id: data.session_id || null }))
        .put(`/admin/submissions/${props.submission.id}/presentation`, { preserveScroll: true, preserveState: true });
}
function clearSlot() {
    slotForm.session_id = '';
    saveSlot();
}
const sessionLabel = (s) => `${formatDate(s.date, { day: 'numeric', month: 'short' })} ${s.start}–${s.end} · ${tr(s.room.name)}${s.track ? ` · ${tr(s.track.name)}` : ''}`;
const isFull = (s) => s.count >= s.capacity && s.id !== publication.value.presentation?.session;

// Camera-ready check (2026-11-12): approve it, or send it back for fixes (with a message, a file optional)
const toCheck = computed(() => status.value === 'camera_ready' && !props.submission.cameraReadyApproved);
const checkForm = useForm({ choice: '', comment: '', attachment: null });
const checkConfirm = ref(false);
function saveCheck() {
    const url = `/admin/submissions/${props.submission.id}/camera-ready/${checkForm.choice === 'approve' ? 'approve' : 'fix'}`;
    checkForm.transform((data) => ({ comment: data.comment || null, attachment: data.choice === 'fix' ? data.attachment : null }))
        .post(url, { preserveScroll: true, forceFormData: true, onSuccess: () => checkForm.reset(), onFinish: () => { checkConfirm.value = false; } });
}

// Rare: withdraw the acceptance, with a reason (the author is told)
const cancelOpen = ref(false);
const cancelForm = useForm({ comment: '' });
function cancelAcceptance() {
    cancelForm.post(`/admin/submissions/${props.submission.id}/cancel-acceptance`, { preserveScroll: true, onSuccess: () => { cancelOpen.value = false; cancelForm.reset(); } });
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
                <p v-if="submission.cameraReadyApproved" class="mt-2 text-sm text-emerald-600 dark:text-emerald-400"><Fa icon="circle-check" class="mr-1" />{{ t('publication.check.approvedOn', { date: formatDate(submission.cameraReadyApproved) }) }}</p>
                <p v-else-if="submission.cameraReadyFix" class="mt-2 text-sm text-orange-600 dark:text-orange-300"><Fa icon="pen" class="mr-1" />{{ t('publication.check.waitingFix') }}</p>
            </div>

            <!-- Step 3 of 3: check the camera-ready file against the reviewers' notes -->
            <form v-if="toCheck" :class="box" class="space-y-3" @submit.prevent="checkConfirm = true">
                <p class="flex items-center gap-2 font-semibold"><Fa icon="magnifying-glass" class="text-gold-500" /> {{ t('publication.check.title') }}</p>
                <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ t('publication.check.intro') }}</p>
                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup">
                    <button
                        v-for="option in [{ value: 'approve', icon: 'circle-check', active: 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' }, { value: 'fix', icon: 'pen', active: 'border-orange-500 bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300' }]"
                        :key="option.value"
                        type="button"
                        role="radio"
                        :aria-checked="checkForm.choice === option.value"
                        :class="checkForm.choice === option.value ? option.active : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                        class="flex flex-col items-center gap-0.5 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                        @click="checkForm.choice = option.value"
                    >
                        <span class="flex items-center gap-2"><Fa :icon="option.icon" /> {{ t(`publication.check.${option.value}`) }}</span>
                        <span class="text-xs font-normal opacity-80">{{ t(`publication.check.${option.value}Next`) }}</span>
                    </button>
                </div>
                <label class="block">
                    <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.screening.comment') }} <template v-if="checkForm.choice === 'fix'"><span class="text-red-500">*</span></template><template v-else>({{ t('submissions.form.optional') }})</template></span>
                    <textarea v-model="checkForm.comment" rows="3" maxlength="5000" :placeholder="t(checkForm.choice === 'fix' ? 'publication.check.fixPlaceholder' : 'publication.check.approvePlaceholder')" :class="[input, border(checkForm.errors.comment), 'w-full']" />
                    <span v-if="checkForm.errors.comment" class="block text-xs text-red-500 dark:text-red-400">{{ t(checkForm.errors.comment) }}</span>
                </label>
                <FilePick v-if="checkForm.choice === 'fix'" v-model="checkForm.attachment" :types="attachmentTypes" :max-mb="maxMb" icon="paperclip" :label="t('decision.attachment')" :hint="t('decision.attachmentHint')" :error="checkForm.errors.attachment" />
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                    :disabled="!checkForm.choice || (checkForm.choice === 'fix' && !checkForm.comment.trim()) || checkForm.processing"
                >
                    <Fa icon="floppy-disk" /> {{ t('publication.check.save') }}
                </button>
            </form>

            <!-- Presentation slot (moves to the Presentations menu once that page reads real data) -->
            <form v-if="canSchedule" :class="box" class="space-y-3" @submit.prevent="saveSlot">
                <p class="flex items-center gap-2 text-sm font-medium"><Fa icon="person-chalkboard" class="text-gold-500" /> {{ t('publication.presentation.title') }}</p>
                <p v-if="publication.presentation" class="text-sm">
                    <Fa icon="calendar-days" class="mr-1 text-gold-500" />{{ formatDate(publication.presentation.at, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }} · {{ publication.presentation.room }}
                </p>
                <p v-if="!sessionOptions.length" class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ t('publication.presentation.noSessions') }} <Link href="/admin/presentations" class="text-gold-600 underline dark:text-gold-400">{{ t('admin.menu.presentations') }}</Link>
                </p>
                <div v-else class="grid gap-2 sm:grid-cols-[1fr_auto]">
                    <select v-model="slotForm.session_id" :class="[input, border(slotForm.errors.session_id)]" :aria-label="t('publication.presentation.session')">
                        <option value="">{{ t('publication.presentation.pickSession') }}</option>
                        <option v-for="s in sessionOptions" :key="s.id" :value="s.id" :disabled="isFull(s)">
                            {{ sessionLabel(s) }} ({{ isFull(s) ? t('admin.presentations.fullShort') : t('admin.presentations.left', { n: s.capacity - s.count }) }})
                        </option>
                    </select>
                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                        :disabled="!slotForm.isDirty || slotForm.processing"
                    >
                        <Fa icon="floppy-disk" /> {{ t('common.save') }}
                    </button>
                </div>
                <span v-if="slotForm.errors.session_id" class="block text-xs text-red-500 dark:text-red-400">{{ t(slotForm.errors.session_id) }}</span>
                <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" />{{ t('publication.presentation.sessionHint') }}</p>
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
            <!-- Rare: withdraw the acceptance (plagiarism found, withdrawn by the author, no camera-ready...) -->
            <button v-if="['accepted', 'camera_ready'].includes(status)" type="button" class="text-xs text-neutral-500 hover:text-red-500 dark:text-neutral-400" @click="cancelOpen = true">
                <Fa icon="ban" class="mr-1" />{{ t('publication.check.cancel') }}
            </button>
        </div>

        <Modal :show="checkConfirm" @close="checkConfirm = false">
            <div class="p-6 sm:p-8">
                <h2 class="text-xl font-semibold">{{ t(`publication.check.confirm.${checkForm.choice || 'approve'}`) }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ t(`publication.check.confirmText.${checkForm.choice || 'approve'}`, { code: submission.code }) }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="checkConfirm = false">{{ t('common.cancel') }}</button>
                    <button type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="checkForm.processing" @click="saveCheck">
                        <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="cancelOpen" @close="cancelOpen = false">
            <form class="p-6 sm:p-8" @submit.prevent="cancelAcceptance">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-red-500/15 text-lg text-red-400"><Fa icon="ban" /></span>
                <h2 class="text-xl font-semibold">{{ t('publication.check.cancelTitle', { code: submission.code }) }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ t('publication.check.cancelText') }}</p>
                <textarea v-model="cancelForm.comment" rows="4" maxlength="5000" required :placeholder="t('publication.check.cancelReason')" class="mt-4 w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm text-white outline-none focus:border-gold-400" />
                <span v-if="cancelForm.errors.comment" class="block text-xs text-red-400">{{ t(cancelForm.errors.comment) }}</span>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="cancelOpen = false">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-600 disabled:opacity-60" :disabled="!cancelForm.comment.trim() || cancelForm.processing">
                        <Fa icon="ban" /> {{ t('publication.check.cancelConfirm') }}
                    </button>
                </div>
            </form>
        </Modal>
    </section>
</template>
