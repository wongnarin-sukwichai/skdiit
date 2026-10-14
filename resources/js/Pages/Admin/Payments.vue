<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import Pagination from '@/Components/Pagination.vue';
import PaymentStatusBadge from '@/Components/PaymentStatusBadge.vue';
import PaymentDetailModal from '@/Components/Admin/Payments/PaymentDetailModal.vue';
import MyPayments from '@/Components/Admin/Payments/MyPayments.vue';
import { useI18n } from '@/i18n';
import { useRegistrantTable } from '@/composables/useRegistrantTable';
import { useModules } from '@/composables/useModules';
import { useToast } from '@/composables/useToast';
import { timeAgo } from '@/composables/useNotifications';
import { adminPayments, paymentFees, paymentStatuses } from '@/data/mock';
import { duplicateSlips, overdueDays as overdueDaysOf } from '@/utils/payments';

// Payments (on hold until the client confirms fees): finance and admin check slips, authors see their own fees
const { t, tr, formatDate, formatNumber } = useI18n();
const { toast } = useToast();
const { accessTo } = useModules();
const isAuthor = computed(() => accessTo('payments') === 'own');
const canReview = computed(() => accessTo('payments') === 'full');

// Mock phase: a reactive copy, so approve/reject show up until the page reloads
const list = reactive(adminPayments.map((p) => ({ ...p, remindedBy: null, remindedAt: null })));
// Fee settings (Settings › Fees, sample): the payment period decides what is overdue
const fees = reactive(paymentFees);

// Unpaid and past the payment period; the same slip used twice (utils/payments, shared with the finance home page)
const overdueDays = (row) => overdueDaysOf(row, fees.dueDays);
const duplicates = computed(() => duplicateSlips(list));

const { form, perPage, page, total, lastPage, rows, filtered, search, reset } = useRegistrantTable(list, {
    withEmail: true,
    filters: { status: '', option: '' },
    match: (row, f) => (!f.status
        || (f.status === 'overdue' ? overdueDays(row) > 0 : f.status === 'duplicate' ? !!duplicates.value[row.id] : row.status === f.status))
        && (!f.option || row.option === f.option),
});

// Opened from the finance home page with a filter: ?filter=pending_review | duplicate | overdue
const initialFilter = new URLSearchParams(window.location.search).get('filter');
if (initialFilter && [...paymentStatuses, 'duplicate', 'overdue'].includes(initialFilter)) {
    form.status = initialFilter;
    search();
}
const stats = computed(() => ({
    invoices: list.length,
    toCheck: list.filter((p) => p.status === 'pending_review').length,
    received: list.filter((p) => p.status === 'paid').reduce((sum, p) => sum + p.amount, 0),
    outstanding: list.filter((p) => p.status !== 'paid').reduce((sum, p) => sum + p.amount, 0),
}));
const statCards = [
    { key: 'invoices', icon: 'receipt' },
    { key: 'toCheck', icon: 'magnifying-glass' },
    { key: 'received', icon: 'circle-check' },
    { key: 'outstanding', icon: 'clock' },
];

// Details and finance's check
const viewing = ref(null);
const find = (id) => list.find((p) => p.id === id);

function review(status, reason = null) {
    const payment = find(viewing.value.id);
    Object.assign(payment, {
        status,
        reviewedAt: new Date().toISOString(),
        reviewedBy: 'Demo Finance',
        rejectReason: reason ? { en: reason, th: reason } : null,
    });
    // Real system: the author is told by email (approved, or why the slip was rejected)
    toast(`admin.payments.toast.${status === 'paid' ? 'approved' : 'rejected'}`, { name: tr(payment.name) });
    viewing.value = null;
}

// Reminder to pay (sample until email is set up): who sent it is kept
function remind(row) {
    Object.assign(row, { remindedBy: usePage().props.auth.user.name, remindedAt: new Date().toISOString() });
    toast('admin.payments.reminded', { email: row.email });
}

// Excel of the rows that match the filters (for accounting)
const exporting = ref(false);
async function exportExcel() {
    exporting.value = true;
    try {
        const xsrf = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        const headers = ['ref', 'payer', 'email', 'paper', 'option', 'amount', 'status', 'slipDate', 'slipBank', 'slipAmount', 'reviewedBy'].map((k) => t(`admin.payments.col.${k}`));
        const body = filtered.value.map((r) => [
            r.ref, tr(r.name), r.email, `${r.paperCode} ${tr(r.paper)}`, t(`admin.payments.optionShort.${r.option}`), String(r.amount),
            t(`payment.status.${r.status}`) + (overdueDays(r) ? ` (${t('admin.payments.overdueDays', { n: overdueDays(r) })})` : ''),
            r.slip ? formatDate(r.slip.transferredAt, { dateStyle: 'medium', timeStyle: 'short' }) : '', r.slip?.bank ?? '', r.slip ? String(r.slip.amount) : '', r.reviewedBy ?? '',
        ]);
        const response = await fetch('/admin/export', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ headers, rows: body, name: 'payments' }),
        });
        if (!response.ok) throw new Error(response.statusText);
        const name = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? 'payments.xlsx';
        const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await response.blob()), download: name });
        link.click();
        URL.revokeObjectURL(link.href);
        toast('admin.registrants.toast.exported', { count: formatNumber(body.length) });
    } catch {
        toast('admin.registrants.toast.exportFailed');
    } finally {
        exporting.value = false;
    }
}

const baht = (value) => `${formatNumber(value)} ${t('admin.payments.baht')}`;
const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 py-2.5 pr-3 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <Head :title="t('admin.menu.payments')" />

    <MyPayments v-if="isAuthor" />

    <div v-else class="space-y-6">
        <Card class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <PageHeader icon="receipt" :title="t('admin.menu.payments')" :subtitle="t('admin.payments.subtitle')" class="flex-1" />
                <button
                    class="flex items-center gap-2 rounded-lg border border-emerald-600/40 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-400/40 dark:text-emerald-300 dark:hover:bg-emerald-400/10"
                    :disabled="exporting || !filtered.length"
                    @click="exportExcel"
                >
                    <Fa icon="file-excel" /> {{ t('admin.payments.export', { count: formatNumber(filtered.length) }) }}
                </button>
            </div>
            <StatCards :stats="stats" :cards="statCards" />
            <p class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                <Fa icon="circle-info" class="mt-0.5" />
                {{ t('admin.payments.feesNote', { a: baht(fees.a), b: baht(fees.b) }) }}
            </p>
        </Card>

        <Card>
            <form class="mb-5 grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto_auto]" @submit.prevent="search">
                <label class="relative">
                    <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                    <input v-model="form.name" :placeholder="t('admin.payments.searchName')" :class="[input, 'pl-11']">
                </label>
                <select v-model="form.status" :class="[input, 'pl-3']" :aria-label="t('registrants.paymentStatus')">
                    <option value="">{{ t('admin.registrants.allPayments') }}</option>
                    <option v-for="s in paymentStatuses" :key="s" :value="s">{{ t(`payment.status.${s}`) }}</option>
                    <optgroup :label="t('admin.payments.special')">
                        <option value="overdue">{{ t('admin.payments.overdueFilter', { n: list.filter((r) => overdueDays(r) > 0).length }) }}</option>
                        <option value="duplicate">{{ t('admin.payments.duplicateFilter', { n: Object.keys(duplicates).length }) }}</option>
                    </optgroup>
                </select>
                <select v-model="form.option" :class="[input, 'pl-3']" :aria-label="t('admin.payments.option')">
                    <option value="">{{ t('admin.payments.allOptions') }}</option>
                    <option v-for="o in ['a', 'b']" :key="o" :value="o">{{ t(`admin.payments.optionName.${o}`) }}</option>
                </select>
                <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-5 py-2.5 transition hover:border-gold-400 dark:border-white/20" @click="reset">
                    <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
                </button>
                <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-6 py-2.5 font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
                </button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <table class="w-full min-w-250 text-sm">
                    <thead class="bg-gold-300 text-left text-black dark:bg-gold-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ t('admin.payments.ref') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('admin.payments.payer') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('admin.payments.paper') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ t('admin.payments.amount') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('admin.payments.slip') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.status') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.actions') }}</th>
                        </tr>
                    </thead>
                    <Transition name="fade" mode="out-in">
                        <tbody :key="`${page}-${perPage}-${total}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                            <tr v-for="row in rows" :key="row.id" class="transition odd:bg-neutral-50 hover:bg-gold-50 dark:odd:bg-white/2 dark:hover:bg-gold-400/5">
                                <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap text-gold-600 dark:text-gold-400">{{ row.ref }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="whitespace-nowrap">{{ tr(row.name) }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ row.email }}</div>
                                </td>
                                <td class="max-w-xs px-3 py-2.5">
                                    <div class="truncate">{{ tr(row.paper) }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                        <span class="font-mono">{{ row.paperCode }}</span> · {{ t(`admin.payments.optionShort.${row.option}`) }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">{{ baht(row.amount) }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-neutral-600 dark:text-neutral-300">
                                    <template v-if="row.slip">
                                        {{ formatDate(row.slip.uploadedAt) }}
                                        <Fa v-if="row.slip.amount !== row.amount" icon="triangle-exclamation" class="ml-1 text-red-500" :title="t('admin.payments.mismatchShort')" />
                                        <span v-if="duplicates[row.id]" class="ml-1.5 inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-500/15 dark:text-red-300" :title="t('admin.payments.duplicateOf', { refs: duplicates[row.id].join(', ') })">
                                            <Fa icon="copy" class="text-[0.65rem]" /> {{ t('admin.payments.duplicate') }}
                                        </span>
                                    </template>
                                    <span v-else class="text-neutral-400">—</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <PaymentStatusBadge :status="row.status" />
                                    <div v-if="overdueDays(row)" class="text-xs font-medium text-red-600 dark:text-red-400">{{ t('admin.payments.overdueDays', { n: overdueDays(row) }) }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex justify-center gap-1.5">
                                        <!-- Overdue: remind the author (who sent the last reminder shows on hover) -->
                                        <button
                                            v-if="overdueDays(row) && canReview"
                                            :class="[iconBtn, 'border-red-400 text-red-600 dark:border-red-500/50 dark:text-red-300']"
                                            :title="row.remindedBy ? t('admin.payments.remindedBy', { name: row.remindedBy, when: timeAgo(row.remindedAt) }) : t('admin.payments.remind')"
                                            @click="remind(row)"
                                        >
                                            <Fa icon="bell" class="text-xs" />
                                        </button>
                                        <button
                                            :class="[iconBtn, row.status === 'pending_review' && canReview ? 'border-gold-400 text-gold-600 dark:border-gold-400 dark:text-gold-400' : '']"
                                            :title="row.status === 'pending_review' && canReview ? t('admin.payments.check') : t('registrants.view')"
                                            @click="viewing = find(row.id)"
                                        >
                                            <Fa :icon="row.status === 'pending_review' && canReview ? 'magnifying-glass' : 'eye'" class="text-xs" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-12 text-center text-neutral-500">{{ t('admin.payments.empty') }}</td>
                            </tr>
                        </tbody>
                    </Transition>
                </table>
            </div>

            <div class="mt-5 flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex items-center gap-3 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ t('registrants.show') }}
                    <select v-model.number="perPage" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-neutral-800 outline-none focus:border-gold-400 dark:border-white/15 dark:bg-ink-800 dark:text-white">
                        <option v-for="n in [10, 25, 50]" :key="n" :value="n">{{ n }}</option>
                    </select>
                    {{ t('registrants.ofTotal', { total: formatNumber(total) }) }}
                </div>
                <Pagination v-model="page" :last-page="lastPage" />
            </div>

            <p class="mt-4 text-xs text-neutral-500 dark:text-neutral-400">
                <Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.registrants.mockNote') }}
            </p>
        </Card>
    </div>

    <PaymentDetailModal
        :payment="viewing"
        :can-review="canReview"
        :duplicate-of="viewing ? duplicates[viewing.id] ?? null : null"
        @close="viewing = null"
        @approve="review('paid')"
        @reject="review('slip_rejected', $event)"
    />
</template>
