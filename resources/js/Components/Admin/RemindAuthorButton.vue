<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import MailPreview from '@/Components/Admin/MailPreview.vue';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';

/*
 * "Send reminder" to this paper's author (2026-11-13): waiting for the journal answer, a revision or the camera-ready
 * file (`remindTask`). Before anything is sent the editor sees the email exactly as it will go out (recipient, subject,
 * Thai then English; SubmissionController::remindAuthorPreview) and where it goes (email + the author's bell).
 * Nothing is sent on its own anywhere in the system. compact: a small button for table rows; otherwise a strip.
 */
const props = defineProps({
    submission: { type: Object, required: true }, // { id, code, remindTask, authorRemindedAt }
    compact: { type: Boolean, default: false },
});

const { t, formatDate } = useI18n();
const { accessTo } = useModules();
const task = computed(() => props.submission.remindTask);
// Editors and admin; the journal role (Selection, full) for a journal offer only
const allowed = computed(() => !!task.value && !usePage().props.impersonation
    && (accessTo('submissions') === 'full' || (task.value === 'journal' && accessTo('selection') === 'full')));
const last = computed(() => props.submission.authorRemindedAt);
const when = (iso) => formatDate(iso, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

const open = ref(false);
const preview = ref(null); // { to: { name, email }, subject, bodyTh, bodyEn }
const failed = ref(false);
const busy = ref(false);

async function show() {
    open.value = true;
    preview.value = null;
    failed.value = false;
    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/remind-author/preview`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(response.statusText);
        preview.value = await response.json();
    } catch {
        failed.value = true;
    }
}
function send() {
    router.post(`/admin/submissions/${props.submission.id}/remind-author`, {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; open.value = false; },
    });
}
</script>

<template>
    <template v-if="allowed">
        <!-- Table row: a small "send reminder" button, with the last reminder underneath -->
        <span v-if="compact" class="inline-flex flex-col items-start gap-0.5">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs bg-gold-400 font-semibold text-black transition hover:bg-gold-300" @click="show">
                <Fa icon="envelope" /> {{ t('remindAuthor.button') }}
            </button>
            <span v-if="last" class="text-[0.7rem] text-neutral-500 dark:text-neutral-400">{{ t('remindAuthor.last', { date: when(last) }) }}</span>
        </span>

        <!-- Paper page: what the author is waited for, the last reminder, and the button -->
        <div v-else class="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm sm:flex-row sm:items-center dark:border-amber-500/40 dark:bg-amber-500/10">
            <p class="flex-1 text-amber-800 dark:text-amber-200">
                <Fa icon="hourglass-half" class="mr-2" />{{ t(`remindAuthor.task.${task}`) }}
                <span class="ml-1 text-xs opacity-80">· {{ last ? t('remindAuthor.last', { date: when(last) }) : t('remindAuthor.never') }}</span>
            </p>
            <slot />
            <button type="button" class="flex shrink-0 items-center justify-center gap-2 rounded-lg px-4 py-2 bg-gold-400 font-semibold text-black transition hover:bg-gold-300" @click="show">
                <Fa icon="envelope" /> {{ t('remindAuthor.button') }}
            </button>
        </div>

        <!-- The email as it will go out, then send -->
        <Modal :show="open" max-width="max-w-2xl" @close="open = false">
            <div class="space-y-4 p-6 sm:p-8">
                <h2 class="flex items-center gap-2 text-xl font-semibold"><Fa icon="envelope" class="text-gold-400" />{{ t('remindAuthor.title') }}</h2>
                <p class="text-sm text-neutral-400">{{ t(`remindAuthor.text.${task}`, { code: submission.code }) }}</p>

                <p v-if="failed" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ t('remindAuthor.previewFailed') }}</p>
                <p v-else-if="!preview" class="py-8 text-center text-sm text-neutral-400"><Fa icon="spinner" spin class="mr-2" />{{ t('remindAuthor.loading') }}</p>
                <MailPreview v-else :preview="preview" @leave="open = false" />

                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="open = false">{{ t('common.cancel') }}</button>
                    <button type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-50" :disabled="!preview || busy" @click="send">
                        <Fa icon="paper-plane" /> {{ t('remindAuthor.send') }}
                    </button>
                </div>
            </div>
        </Modal>
    </template>
</template>
