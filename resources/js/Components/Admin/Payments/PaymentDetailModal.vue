<script setup>
import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PaymentStatusBadge from '@/Components/PaymentStatusBadge.vue';
import { useI18n } from '@/i18n';
import { affiliationByKey } from '@/data/mock';

/**
 * One fee (mock: adminPayments): payer, paper, the uploaded slip, and finance's check.
 * Finance approves (paid) or rejects with a reason (the author uploads a new slip).
 */
const props = defineProps({
    payment: { type: Object, default: null },
    canReview: { type: Boolean, default: false },
    duplicateOf: { type: Array, default: null }, // other invoices with the same slip
});
const emit = defineEmits(['close', 'approve', 'reject']);

const { t, tr, formatDate, formatNumber } = useI18n();
const when = (value) => formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });

// Keep the last record so the content stays visible while the modal fades out
const shown = ref(null);
const rejecting = ref(false);
const reason = ref('');
const reasonError = ref(false);
watch(() => props.payment, (value) => {
    if (value) shown.value = value;
    rejecting.value = false;
    reason.value = '';
    reasonError.value = false;
}, { immediate: true });

const amountMismatch = computed(() => shown.value?.slip && shown.value.slip.amount !== shown.value.amount);

function reject() {
    if (!reason.value.trim()) {
        reasonError.value = true;
        return;
    }
    emit('reject', reason.value.trim());
}

const baht = (value) => `${formatNumber(value)} ${t('admin.payments.baht')}`;
const dt = 'text-neutral-400';
</script>

<template>
    <Modal :show="!!payment" max-width="max-w-2xl" @close="emit('close')">
        <div v-if="shown" class="p-6 sm:p-8">
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-full bg-gold-400/15 text-xl text-gold-400">
                    <Fa icon="receipt" />
                </span>
                <div class="flex-1">
                    <p class="font-mono text-xs tracking-wider text-gold-400">{{ shown.ref }}</p>
                    <h3 class="text-xl font-semibold">{{ tr(shown.name) }}</h3>
                    <p class="text-sm text-neutral-400">{{ shown.email }} · {{ tr(affiliationByKey[shown.affiliation]) }}</p>
                </div>
                <PaymentStatusBadge :status="shown.status" />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt :class="dt">{{ t('admin.payments.paper') }}</dt>
                        <dd class="mt-0.5"><span class="font-mono text-xs text-gold-400">{{ shown.paperCode }}</span> {{ tr(shown.paper) }}</dd>
                    </div>
                    <div>
                        <dt :class="dt">{{ t('admin.payments.option') }}</dt>
                        <dd class="mt-0.5">{{ t(`admin.payments.optionName.${shown.option}`) }}</dd>
                    </div>
                    <div>
                        <dt :class="dt">{{ t('admin.payments.amount') }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold">{{ baht(shown.amount) }}</dd>
                    </div>
                    <div>
                        <dt :class="dt">{{ t('admin.payments.issuedAt') }}</dt>
                        <dd class="mt-0.5">{{ when(shown.issuedAt) }}</dd>
                    </div>
                    <div v-if="shown.reviewedAt">
                        <dt :class="dt">{{ t('admin.payments.reviewedAt') }}</dt>
                        <dd class="mt-0.5">{{ when(shown.reviewedAt) }} · {{ shown.reviewedBy }}</dd>
                    </div>
                    <div v-if="shown.rejectReason">
                        <dt :class="dt">{{ t('admin.payments.rejectReason') }}</dt>
                        <dd class="mt-0.5 text-red-300">{{ tr(shown.rejectReason) }}</dd>
                    </div>
                </dl>

                <!-- Slip: the real system shows the uploaded image; the sample draws a stand-in -->
                <div>
                    <p :class="dt" class="mb-2 text-sm">{{ t('admin.payments.slip') }}</p>
                    <div v-if="shown.slip" class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="font-semibold">{{ shown.slip.bank }}</span>
                            <Fa icon="circle-check" class="text-emerald-400" />
                        </div>
                        <p class="text-xs text-neutral-400">{{ t('admin.payments.transferredAt') }}</p>
                        <p class="mb-2">{{ when(shown.slip.transferredAt) }}</p>
                        <p class="text-xs text-neutral-400">{{ t('admin.payments.slipAmount') }}</p>
                        <p :class="amountMismatch ? 'text-red-400' : 'text-emerald-300'" class="text-2xl font-bold">{{ baht(shown.slip.amount) }}</p>
                        <p v-if="amountMismatch" class="mt-1 text-xs text-red-300">
                            <Fa icon="triangle-exclamation" class="mr-1" /> {{ t('admin.payments.mismatch', { amount: baht(shown.amount) }) }}
                        </p>
                        <p v-if="duplicateOf" class="mt-2 rounded-md border border-red-500/40 bg-red-500/10 px-2.5 py-1.5 text-xs text-red-300">
                            <Fa icon="copy" class="mr-1" /> {{ t('admin.payments.duplicateWarning', { refs: duplicateOf.join(', ') }) }}
                        </p>
                        <p class="mt-3 truncate border-t border-white/10 pt-2 text-xs text-neutral-500">
                            <Fa icon="image" class="mr-1" /> {{ shown.slip.fileName }} · {{ t('admin.payments.uploadedAt', { date: when(shown.slip.uploadedAt) }) }}
                        </p>
                    </div>
                    <p v-else class="rounded-xl border border-dashed border-white/15 px-4 py-10 text-center text-sm text-neutral-500">{{ t('admin.payments.noSlip') }}</p>
                </div>
            </div>

            <!-- Finance's check (only while a slip waits) -->
            <div v-if="canReview && shown.status === 'pending_review'" class="mt-6 border-t border-white/10 pt-5">
                <Transition name="fade" mode="out-in">
                    <div v-if="rejecting" key="reject">
                        <label for="reject-reason" class="mb-1.5 block text-sm font-medium">{{ t('admin.payments.rejectReason') }}</label>
                        <textarea
                            id="reject-reason"
                            v-model="reason"
                            rows="3"
                            :placeholder="t('admin.payments.rejectPlaceholder')"
                            :class="reasonError ? 'border-red-500/70' : 'border-white/10'"
                            class="w-full rounded-lg border bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400"
                            @input="reasonError = false"
                        />
                        <p v-if="reasonError" class="mt-1 text-xs text-red-400">{{ t('validation.required') }}</p>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="rejecting = false">{{ t('common.cancel') }}</button>
                            <button type="button" class="flex items-center gap-2 rounded-lg bg-red-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400" @click="reject">
                                <Fa icon="xmark" /> {{ t('admin.payments.reject') }}
                            </button>
                        </div>
                    </div>
                    <div v-else key="actions" class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="flex items-center gap-2 rounded-lg border border-red-500/50 px-4 py-2.5 text-sm text-red-300 transition hover:bg-red-500/10" @click="rejecting = true">
                            <Fa icon="xmark" /> {{ t('admin.payments.reject') }}
                        </button>
                        <button type="button" class="flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-400" @click="emit('approve')">
                            <Fa icon="check" /> {{ t('admin.payments.approve') }}
                        </button>
                    </div>
                </Transition>
            </div>
        </div>
    </Modal>
</template>
