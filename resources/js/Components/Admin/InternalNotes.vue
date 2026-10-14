<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { timeAgo } from '@/composables/useNotifications';

// Internal notes on a paper (SubmissionNoteController): an icon with a count; the notes open as a timeline.
// Admin and editors only, never authors or reviewers.
const props = defineProps({
    submissionId: { type: Number, required: true },
    notes: { type: Array, required: true }, // SubmissionNote::toClient(), pinned first, then newest
});

const { t, formatDate } = useI18n();
const open = ref(false);
const form = useForm({ body: '' });
const options = { preserveScroll: true, preserveState: true };

const add = () => form.post(`/admin/submissions/${props.submissionId}/notes`, { ...options, onSuccess: () => form.reset() });
const pin = (note) => router.put(`/admin/submissions/${props.submissionId}/notes/${note.id}/pin`, {}, options);
const remove = (note) => router.delete(`/admin/submissions/${props.submissionId}/notes/${note.id}`, options);
const when = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <button
        type="button"
        class="relative inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-800 transition hover:border-amber-400 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/15"
        :title="t('notes.private')"
        @click="open = true"
    >
        <Fa icon="note-sticky" /> {{ t('notes.title') }}
        <span v-if="notes.length" class="grid h-5 min-w-5 place-items-center rounded-full bg-amber-500 px-1.5 text-xs font-bold text-white">{{ notes.length }}</span>
    </button>

    <Modal :show="open" max-width="max-w-2xl" @close="open = false">
        <div class="p-6 sm:p-8">
            <h2 class="flex items-center gap-2 pr-10 text-xl font-semibold">
                <Fa icon="note-sticky" class="text-amber-400" /> {{ t('notes.title') }}
            </h2>
            <p class="mt-1 mb-5 text-xs text-neutral-400"><Fa icon="lock" class="mr-1" />{{ t('notes.private') }}</p>

            <!-- New note -->
            <form class="mb-6" @submit.prevent="add">
                <textarea
                    v-model="form.body"
                    rows="3"
                    :placeholder="t('notes.placeholder')"
                    :class="form.errors.body ? 'border-red-500/70' : 'border-white/10'"
                    class="w-full rounded-lg border bg-ink-800 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20"
                    @keydown.ctrl.enter="form.body.trim() && add()"
                />
                <div class="mt-2 flex items-center justify-between gap-2">
                    <span class="text-xs text-red-400">{{ form.errors.body ? t(form.errors.body) : '' }}</span>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40" :disabled="form.processing || !form.body.trim()">
                        <Fa icon="plus" /> {{ t('notes.add') }}
                    </button>
                </div>
            </form>

            <!-- Timeline: pinned first, then newest -->
            <TransitionGroup v-if="notes.length" name="fade" tag="ol" class="relative max-h-[50vh] space-y-5 overflow-y-auto border-l border-white/10 pr-1 pl-6">
                <li v-for="note in notes" :key="note.id" class="relative">
                    <span
                        :class="note.pinned ? 'border-amber-400 bg-amber-400 text-black' : 'border-white/20 bg-ink-900 text-amber-300'"
                        class="absolute top-0.5 -left-9.25 grid size-7 place-items-center rounded-full border text-[0.7rem]"
                    >
                        <Fa :icon="note.pinned ? 'thumbtack' : 'note-sticky'" />
                    </span>
                    <div class="flex flex-wrap items-center gap-x-2 text-xs text-neutral-400">
                        <span class="font-semibold text-neutral-100">{{ note.author?.name ?? '—' }}</span>
                        <span v-if="note.author">({{ t(`admin.role.${note.author.role}`) }})</span>
                        <span :title="when(note.at)">· {{ timeAgo(note.at) }}</span>
                        <span class="ml-auto flex gap-1">
                            <button type="button" class="grid size-7 place-items-center rounded hover:bg-white/10 hover:text-amber-300" :title="t(note.pinned ? 'notes.unpin' : 'notes.pin')" @click="pin(note)">
                                <Fa icon="thumbtack" :class="note.pinned ? 'text-amber-400' : ''" />
                            </button>
                            <button v-if="note.canDelete" type="button" class="grid size-7 place-items-center rounded hover:bg-red-500/10 hover:text-red-400" :title="t('notes.delete')" @click="remove(note)">
                                <Fa icon="trash-can" />
                            </button>
                        </span>
                    </div>
                    <p :class="note.pinned ? 'border-amber-400/40 bg-amber-400/10' : 'border-white/10 bg-white/5'" class="mt-1.5 rounded-lg border px-3 py-2 text-sm whitespace-pre-line text-neutral-100">{{ note.body }}</p>
                </li>
            </TransitionGroup>
            <p v-else class="py-6 text-center text-sm text-neutral-500">{{ t('notes.empty') }}</p>
        </div>
    </Modal>
</template>
