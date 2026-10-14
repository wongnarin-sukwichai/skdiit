<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { timeAgo } from '@/composables/useNotifications';
import { usePage } from '@inertiajs/vue3';
import { activityLog as fullLog } from '@/data/mock';

/*
 * Activity log (admin only): who did what, when, from where - across sign-ins, accounts, papers, reviews,
 * payments, settings and "view as this user". Mock until wired to the real event tables.
 */
const { t, tr, formatDate, formatNumber } = useI18n();
const { toast } = useToast();

const isAdmin = usePage().props.auth.user.accessRole === 'admin';
const activityLog = isAdmin ? fullLog : fullLog.filter((e) => ['paper', 'review'].includes(e.category) || e.action === 'registered');
const categories = isAdmin ? ['auth', 'account', 'paper', 'review', 'payment', 'system', 'impersonation'] : ['account', 'paper', 'review'];
const categoryIcons = { auth: 'right-to-bracket', account: 'user', paper: 'file-lines', review: 'clipboard-check', payment: 'receipt', system: 'gear', impersonation: 'user-secret' };
const roles = isAdmin ? ['admin', 'editor', 'reviewer', 'finance', 'author', 'system'] : ['editor', 'reviewer', 'author'];

const blank = { q: '', category: '', role: '', from: '', to: '', flagged: false };
const filters = reactive({ ...blank });
const page = ref(1);
const perPage = 20;

const day = (iso) => new Date(iso).toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });
const filtered = computed(() => {
    const q = filters.q.trim().toLowerCase();
    return activityLog.filter((e) => (!filters.category || e.category === filters.category)
        && (!filters.role || e.actor.role === filters.role)
        && (!filters.flagged || e.flagged)
        && (!filters.from || day(e.at) >= filters.from)
        && (!filters.to || day(e.at) <= filters.to)
        && (!q || `${e.actor.name} ${e.actor.email} ${e.target ?? ''} ${e.ip} ${e.detail?.th ?? ''} ${e.detail?.en ?? ''}`.toLowerCase().includes(q)));
});
const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));
const rows = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage));
watch(filters, () => (page.value = 1));

const countFor = (category) => activityLog.filter((e) => !category || e.category === category).length;
const flaggedCount = activityLog.filter((e) => e.flagged).length;
const roleLabel = (role) => (role === 'system' ? t('admin.activityLog.system') : t(`admin.role.${role}`));

// ---- Excel (rows that match the filters) ----
const exporting = ref(false);
async function exportExcel() {
    exporting.value = true;
    try {
        const xsrf = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        const headers = ['time', 'who', 'role', 'action', 'target', 'detail', ...(isAdmin ? ['ip', 'device'] : [])].map((k) => t(`admin.activityLog.col.${k}`));
        const body = filtered.value.map((e) => [
            formatDate(e.at, { dateStyle: 'medium', timeStyle: 'medium' }), e.actor.name, roleLabel(e.actor.role), t(`admin.activityLog.action.${e.action}`),
            e.target ?? '', e.detail ? tr(e.detail) : '', ...(isAdmin ? [e.ip, e.device] : []),
        ]);
        const response = await fetch('/admin/export', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ headers, rows: body, name: 'activity-log' }),
        });
        if (!response.ok) throw new Error(response.statusText);
        const name = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? 'activity-log.xlsx';
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

const input = 'rounded-lg border border-neutral-300 bg-neutral-50 py-2.5 pr-3 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800 dark:scheme-dark';
</script>

<template>
    <Head :title="t('admin.menu.activity-log')" />

    <Card class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <PageHeader icon="clock-rotate-left" :title="t('admin.menu.activity-log')" :subtitle="t('admin.activityLog.subtitle')" class="flex-1" />
            <button
                class="flex items-center gap-2 rounded-lg border border-emerald-600/40 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-400/40 dark:text-emerald-300 dark:hover:bg-emerald-400/10"
                :disabled="exporting || !filtered.length"
                @click="exportExcel"
            >
                <Fa icon="file-excel" /> {{ t('admin.activityLog.export', { count: formatNumber(filtered.length) }) }}
            </button>
        </div>

        <!-- Category chips -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="key in ['', ...categories]"
                :key="key || 'all'"
                :class="filters.category === key ? 'border-gold-400 bg-gold-400 text-black' : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                class="flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm transition"
                @click="filters.category = key"
            >
                <Fa v-if="key" :icon="categoryIcons[key]" class="text-xs" />
                {{ t(`admin.activityLog.category.${key || 'all'}`) }} ({{ formatNumber(countFor(key)) }})
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="relative min-w-60 flex-1">
                <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                <input v-model="filters.q" :placeholder="t('admin.activityLog.search')" :class="[input, 'w-full pl-11']">
            </label>
            <select v-model="filters.role" :class="[input, 'pl-3']" :aria-label="t('admin.activityLog.col.role')">
                <option value="">{{ t('admin.accounts.allRoles') }}</option>
                <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                {{ t('admin.registrants.from') }} <input v-model="filters.from" type="date" :max="filters.to || undefined" :class="[input, 'pl-3']">
            </label>
            <label class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                {{ t('admin.registrants.to') }} <input v-model="filters.to" type="date" :min="filters.from || undefined" :class="[input, 'pl-3']">
            </label>
            <label v-if="isAdmin" class="flex cursor-pointer items-center gap-2 rounded-lg border border-red-200 px-3 py-2.5 text-sm text-red-700 dark:border-red-500/30 dark:text-red-300">
                <input v-model="filters.flagged" type="checkbox" class="size-4 accent-red-500">
                <Fa icon="flag" /> {{ t('admin.activityLog.flaggedOnly', { n: flaggedCount }) }}
            </label>
            <button type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20" @click="Object.assign(filters, blank)">
                <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-275 text-sm">
                <thead class="bg-gold-300 text-left text-black dark:bg-gold-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ t('admin.activityLog.col.time') }}</th>
                        <th class="px-3 py-3 font-semibold">{{ t('admin.activityLog.col.who') }}</th>
                        <th class="px-3 py-3 font-semibold">{{ t('admin.activityLog.col.action') }}</th>
                        <th class="px-3 py-3 font-semibold">{{ t('admin.activityLog.col.target') }}</th>
                        <th class="px-3 py-3 font-semibold">{{ t('admin.activityLog.col.detail') }}</th>
                        <th v-if="isAdmin" class="px-3 py-3 font-semibold">{{ t('admin.activityLog.col.where') }}</th>
                    </tr>
                </thead>
                <Transition name="fade" mode="out-in">
                    <tbody :key="`${page}-${filtered.length}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="e in rows" :key="e.id" :class="e.flagged ? 'bg-red-50/70 dark:bg-red-500/5' : 'odd:bg-neutral-50 dark:odd:bg-white/2'">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div>{{ timeAgo(e.at) }}</div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(e.at, { dateStyle: 'medium', timeStyle: 'short' }) }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium">{{ e.actor.name }}</div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ roleLabel(e.actor.role) }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center gap-2 whitespace-nowrap">
                                    <Fa :icon="categoryIcons[e.category]" :class="e.flagged ? 'text-red-500' : 'text-gold-500'" class="w-4" />
                                    {{ t(`admin.activityLog.action.${e.action}`) }}
                                    <Fa v-if="e.flagged" icon="flag" class="text-xs text-red-500" :title="t('admin.activityLog.flagged')" />
                                </span>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs break-all">{{ e.target ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-neutral-600 dark:text-neutral-300">{{ e.detail ? tr(e.detail) : '—' }}</td>
                            <td v-if="isAdmin" class="px-3 py-2.5 whitespace-nowrap">
                                <div class="font-mono text-xs">{{ e.ip }}</div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ e.device }}</div>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="isAdmin ? 6 : 5" class="px-4 py-12 text-center text-neutral-500">{{ t('admin.activityLog.empty') }}</td>
                        </tr>
                    </tbody>
                </Transition>
            </table>
        </div>

        <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.activityLog.count', { count: formatNumber(filtered.length) }) }}</p>
            <Pagination v-model="page" :last-page="lastPage" />
        </div>

        <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.activityLog.mockNote') }}</p>
    </Card>
</template>
