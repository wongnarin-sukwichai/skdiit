<script setup>
import { reactive, ref } from 'vue';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PaymentStatusBadge from '@/Components/PaymentStatusBadge.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { myPayments } from '@/data/mock';

// Author view of Payments: one fee per paper set for publication; pay by transfer, then upload the slip
const { t, tr, formatDate, formatNumber } = useI18n();
const { toast } = useToast();

// Mock phase: uploads change the sample until the page reloads
const list = reactive(myPayments.map((p) => ({ ...p })));
const picker = ref(null);
const uploadingFor = ref(null);

function chooseSlip(payment) {
    uploadingFor.value = payment.id;
    picker.value.value = '';
    picker.value.click();
}

function onSlip(event) {
    const file = event.target.files[0];
    if (!file) return;
    if (!/\.(jpe?g|png|pdf)$/i.test(file.name)) {
        toast('admin.payments.mine.wrongType');
        return;
    }
    // Real system: stored privately, finance is notified; the amount is read by finance from the slip
    const payment = list.find((p) => p.id === uploadingFor.value);
    const now = new Date().toISOString();
    Object.assign(payment, {
        status: 'pending_review',
        slip: { uploadedAt: now, transferredAt: now, amount: payment.amount, bank: '—', fileName: file.name },
        rejectReason: null,
    });
    toast('admin.payments.mine.uploaded');
}

const baht = (value) => `${formatNumber(value)} ${t('admin.payments.baht')}`;
const canUpload = (payment) => ['awaiting_payment', 'slip_rejected'].includes(payment.status);
</script>

<template>
    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="receipt" :title="t('admin.menu.payments')" :subtitle="t('admin.payments.mine.subtitle')" />

            <!-- Where to transfer (sample account until the client confirms) -->
            <div class="grid gap-4 rounded-xl border border-gold-300 bg-gold-50 p-5 text-sm sm:grid-cols-3 dark:border-gold-400/30 dark:bg-gold-400/10">
                <div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.payments.mine.bank') }}</p>
                    <p class="font-semibold">{{ t('admin.payments.mine.bankName') }}</p>
                </div>
                <div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.payments.mine.accountName') }}</p>
                    <p class="font-semibold">{{ t('admin.payments.mine.accountHolder') }}</p>
                </div>
                <div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.payments.mine.accountNo') }}</p>
                    <p class="font-mono font-semibold tracking-wider">123-4-56789-0</p>
                </div>
            </div>

            <!-- One row per fee, like "My submissions": blue bar and pill when it is the author's move -->
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <table class="w-full min-w-225 text-sm">
                    <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('admin.payments.ref') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('admin.payments.paper') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('admin.payments.amount') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('registrants.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('submissions.next.column') }}</th>
                            <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="payment in list" :key="payment.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                            <td :class="canUpload(payment) ? 'border-sky-400' : 'border-transparent'" class="border-l-4 px-4 py-3 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ payment.ref }}</td>
                            <td class="max-w-md px-4 py-3">
                                <p class="line-clamp-2 font-medium">{{ tr(payment.paper) }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400"><span class="font-mono">{{ payment.paperCode }}</span> · {{ t(`admin.payments.optionShort.${payment.option}`) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">{{ baht(payment.amount) }}</td>
                            <td class="px-4 py-3 text-center"><PaymentStatusBadge :status="payment.status" /></td>
                            <td class="px-4 py-3">
                                <template v-if="canUpload(payment)">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-300 bg-sky-50 px-3 py-0.5 text-xs whitespace-nowrap text-sky-700 dark:border-sky-400/50 dark:bg-sky-400/10 dark:text-sky-200">
                                        <Fa icon="user-pen" class="text-[0.7rem]" /> {{ t(payment.status === 'slip_rejected' ? 'admin.payments.mine.uploadAgain' : 'admin.payments.mine.payAndUpload') }}
                                    </span>
                                    <p v-if="payment.status === 'slip_rejected'" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ t('admin.payments.mine.rejected') }} {{ tr(payment.rejectReason) }}</p>
                                </template>
                                <span v-else-if="payment.status === 'pending_review'" class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.payments.mine.checking', { date: formatDate(payment.slip.uploadedAt) }) }}</span>
                                <span v-else class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.payments.mine.paidOn', { date: formatDate(payment.reviewedAt) }) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="canUpload(payment)"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-gold-400 px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-black transition hover:bg-gold-300"
                                    @click="chooseSlip(payment)"
                                >
                                    <Fa icon="file-arrow-up" /> {{ t('admin.payments.mine.upload') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!list.length">
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">{{ t('admin.payments.mine.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <p class="text-xs text-neutral-500 dark:text-neutral-400">
            <Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.payments.mine.note') }}
        </p>
        <input ref="picker" type="file" accept=".jpg,.jpeg,.png,.pdf" class="hidden" @change="onSlip">
    </div>
</template>
