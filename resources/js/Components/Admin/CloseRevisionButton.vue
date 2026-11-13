<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import MailPreview from '@/Components/Admin/MailPreview.vue';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';

/*
 * "Close" a revision the author never sent (2026-11-13): the paper becomes "not accepted" and the author is told why.
 * Editors type the reason and see the email update as they type (SubmissionController::closeRevisionPreview).
 */
const props = defineProps({
    submission: { type: Object, required: true }, // { id, code, canCloseRevision }
});

const { t } = useI18n();
const { accessTo } = useModules();

const open = ref(false);
const form = useForm({ comment: '' });
const preview = ref(null);
let timer = null;
async function load() {
    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/close-revision/preview?comment=${encodeURIComponent(form.comment)}`, { headers: { Accept: 'application/json' } });
        preview.value = response.ok ? await response.json() : null;
    } catch {
        preview.value = null;
    }
}
watch(() => form.comment, () => {
    clearTimeout(timer);
    timer = setTimeout(load, 400);
});
function show() {
    form.reset();
    preview.value = null;
    open.value = true;
    load();
}
function close() {
    form.post(`/admin/submissions/${props.submission.id}/close-revision`, { preserveScroll: true, onSuccess: () => { open.value = false; } });
}
</script>

<template>
    <template v-if="submission.canCloseRevision && accessTo('submissions') === 'full'">
        <button type="button" class="flex shrink-0 items-center justify-center gap-2 rounded-lg border border-red-400 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10" @click="show">
            <Fa icon="ban" /> {{ t('closeRevision.button') }}
        </button>

        <Modal :show="open" max-width="max-w-2xl" @close="open = false">
            <form class="space-y-4 p-6 sm:p-8" @submit.prevent="close">
                <h2 class="flex items-center gap-2 text-xl font-semibold"><Fa icon="ban" class="text-red-400" />{{ t('closeRevision.title') }}</h2>
                <p class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ t('closeRevision.intro', { code: submission.code }) }}</p>
                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium">{{ t('closeRevision.reason') }} <span class="text-red-400">*</span></span>
                    <textarea id="close-revision-reason" v-model="form.comment" rows="3" maxlength="5000" :placeholder="t('closeRevision.reasonPlaceholder')" class="w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400" />
                    <span v-if="form.errors.comment" class="mt-1 block text-xs text-red-400">{{ t(form.errors.comment) }}</span>
                </label>
                <div>
                    <p class="mb-2 text-sm font-medium">{{ t('closeRevision.preview') }}</p>
                    <MailPreview v-if="preview" :preview="preview" @leave="open = false" />
                    <p v-else class="py-6 text-center text-sm text-neutral-400"><Fa icon="spinner" spin class="mr-2" />{{ t('remindAuthor.loading') }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="open = false">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-red-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400 disabled:opacity-50" :disabled="!form.comment.trim() || form.processing">
                        <Fa icon="ban" /> {{ t('closeRevision.confirm') }}
                    </button>
                </div>
            </form>
        </Modal>
    </template>
</template>
