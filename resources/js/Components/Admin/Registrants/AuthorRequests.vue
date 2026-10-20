<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';
import { useAffiliations } from '@/composables/useAffiliations';

/**
 * "Submit requests" tab: attend-only registrants who ask to submit papers (AuthorRequest).
 * Admin approves (participation becomes attend_submit) or declines with an optional reason; both are emailed.
 */
defineProps({
    requests: { type: Array, required: true }, // AuthorRequest::toClient, pending first
    canEdit: { type: Boolean, default: false },
});

const { t, formatDate } = useI18n();
const { affiliationOf } = useAffiliations();

const pending = ref(null); // { action: 'approve' | 'reject', request }
const shown = ref(null); // keeps the text while the modal fades out
const note = ref('');
const busy = ref(false);
watch(pending, (value) => {
    if (!value) return;
    shown.value = value;
    note.value = '';
});

function confirm() {
    const { action, request } = pending.value;
    router.post(`/admin/registrants/requests/${request.id}/${action}`, action === 'reject' ? { note: note.value.trim() || null } : {}, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => {
            busy.value = false;
            pending.value = null;
        },
    });
}

const statusClass = {
    pending: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300',
    approved: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300',
    rejected: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-300',
};
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">
            <Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.registrants.requests.intro') }}
        </p>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-200 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('registrants.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.registrants.requests.reason') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.registrants.requests.askedAt') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                        <th v-if="canEdit" class="px-4 py-3 text-center font-medium">{{ t('registrants.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="request in requests" :key="request.id" class="align-top transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                        <td class="px-4 py-3">
                            <span class="block font-medium">{{ request.name }}</span>
                            <span class="block text-xs text-neutral-500 dark:text-neutral-400">{{ request.email }}</span>
                            <span class="block text-xs text-neutral-500 dark:text-neutral-400">{{ affiliationOf(request) }}</span>
                        </td>
                        <td class="max-w-md px-4 py-3 whitespace-pre-line">
                            <span v-if="request.reason">{{ request.reason }}</span>
                            <span v-else class="text-neutral-400 italic">{{ t('admin.registrants.requests.noReason') }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ formatDate(request.at, { dateStyle: 'medium', timeStyle: 'short' }) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="statusClass[request.status]" class="inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap">
                                {{ t(`admin.registrants.requests.status.${request.status}`) }}
                            </span>
                            <span v-if="request.decidedAt" class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">
                                {{ t('admin.registrants.requests.decidedBy', { name: request.decidedBy ?? '—', date: formatDate(request.decidedAt) }) }}
                            </span>
                            <span v-if="request.note" class="mt-1 block max-w-56 text-left text-xs whitespace-pre-line text-neutral-500 dark:text-neutral-400">{{ request.note }}</span>
                        </td>
                        <td v-if="canEdit" class="px-4 py-3">
                            <div v-if="request.status === 'pending'" class="flex justify-center gap-2">
                                <button class="flex items-center gap-1.5 rounded-lg bg-gold-400 px-3 py-1.5 text-xs font-semibold text-black transition hover:bg-gold-300" @click="pending = { action: 'approve', request }">
                                    <Fa icon="check" /> {{ t('admin.registrants.requests.approve') }}
                                </button>
                                <button class="flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10" @click="pending = { action: 'reject', request }">
                                    <Fa icon="xmark" /> {{ t('admin.registrants.requests.reject') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!requests.length">
                        <td :colspan="canEdit ? 5 : 4" class="px-4 py-12 text-center text-neutral-500">{{ t('admin.registrants.requests.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ConfirmModal
            :show="!!pending"
            :icon="shown?.action === 'reject' ? 'xmark' : 'file-arrow-up'"
            :danger="shown?.action === 'reject'"
            :processing="busy"
            :title="shown ? t(shown.action === 'reject' ? 'admin.registrants.requests.rejectTitle' : 'admin.registrants.requests.confirmApprove.title') : ''"
            :message="shown ? t(shown.action === 'reject' ? 'admin.registrants.requests.rejectMessage' : 'admin.registrants.requests.confirmApprove.message', { name: shown.request.name }) : ''"
            :confirm-label="shown ? t(`admin.registrants.requests.${shown.action}`) : ''"
            @close="pending = null"
            @confirm="confirm"
        >
            <label v-if="shown?.action === 'reject'" class="mt-4 block">
                <span class="mb-1.5 block text-sm text-neutral-300">{{ t('admin.registrants.requests.rejectNote') }}</span>
                <textarea v-model="note" rows="3" maxlength="2000" class="w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm text-white outline-none focus:border-gold-400" />
            </label>
        </ConfirmModal>
    </div>
</template>
