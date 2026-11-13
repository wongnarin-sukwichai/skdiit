<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { timeAgo } from '@/composables/useNotifications';
import { eventTitle } from '@/utils/submissionEvents';

/*
 * Activity log (real since 2026-11-13, ActivityLogController): sign-ins, accounts, settings, "view as this user",
 * new year and backup (ActivityLog) together with every paper's history, filtered and paged on the server.
 * Admin sees everything with IP and device; editors see paper and review activity and new registrations.
 */
const props = defineProps({
    entries: { type: Object, required: true }, // paginator
    filters: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) }, // per category
    flaggedCount: { type: Number, default: 0 },
});

const { t, formatDate, formatNumber } = useI18n();
const { toast } = useToast();

const isAdmin = usePage().props.auth.user.accessRole === 'admin';
const categories = isAdmin ? ['auth', 'account', 'paper', 'review', 'system', 'impersonation'] : ['account', 'paper', 'review'];
const categoryIcons = { auth: 'right-to-bracket', account: 'user', paper: 'file-lines', review: 'clipboard-check', system: 'gear', impersonation: 'user-secret' };
const roles = isAdmin ? ['admin', 'editor', 'journal', 'reviewer', 'author', 'system'] : ['editor', 'reviewer', 'author'];

const blank = { q: '', category: '', role: '', from: '', to: '', flagged: false };
const filters = reactive({ ...blank, ...props.filters, flagged: props.filters.flagged === '1' });
const page = ref(props.entries.current_page);
watch(() => props.entries.current_page, (p) => { page.value = p; });
const rows = computed(() => props.entries.data);
const lastPage = computed(() => Math.max(1, props.entries.last_page));

const query = (p = 1) => ({
    ...Object.fromEntries(Object.entries(filters).filter(([k, v]) => v && k !== 'flagged')),
    flagged: filters.flagged ? '1' : undefined,
    page: p > 1 ? p : undefined,
});
function load(p = 1) {
    router.get('/admin/activity-log', query(p), { preserveState: true, preserveScroll: true, replace: true });
}
let timer = null;
watch(() => ({ ...filters }), (now, before) => {
    clearTimeout(timer);
    timer = setTimeout(() => load(1), now.q !== before.q ? 350 : 0);
});
watch(page, (p) => p !== props.entries.current_page && load(p));
const reset = () => Object.assign(filters, blank);

const total = computed(() => Object.values(props.counts).reduce((n, c) => n + Number(c), 0));
const countFor = (category) => (category ? Number(props.counts[category] ?? 0) : total.value);
const roleLabel = (role) => (!role || role === 'system' ? t('admin.activityLog.system') : t(`admin.role.${role}`));

// Paper history rows use the paper's own history labels; the rest have their own
const actionLabel = (e) => (e.event ? eventTitle(t, e.event) : t(`admin.activityLog.action.${e.action}`));
const detailLabel = (e) => {
    if (!e.detail) return '—';
    if (e.action === 'impersonation_start') return roleLabel(e.detail);
    if (['settings_changed'].includes(e.action) || e.category === 'account') {
        const key = `admin.activityLog.section.${e.detail.replace('.', '_')}`;
        const label = t(key);
        return label === key ? e.detail : label;
    }
    return e.detail;
};

// ---- Excel (every row that matches the filters, up to 5,000) ----
const exporting = ref(false);
async function exportExcel() {
    exporting.value = true;
    try {
        const xsrf = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        const params = new URLSearchParams(Object.entries(query()).filter(([, v]) => v !== undefined));
        const all = await (await fetch(`/admin/activity-log/rows?${params}`, { headers: { Accept: 'application/json' } })).json();
        const headers = ['time', 'who', 'role', 'action', 'target', 'detail', ...(isAdmin ? ['ip', 'device'] : [])].map((k) => t(`admin.activityLog.col.${k}`));
        const body = all.map((e) => [
            formatDate(e.at, { dateStyle: 'medium', timeStyle: 'medium' }), e.actor.name ?? t('admin.activityLog.system'), roleLabel(e.actor.role), actionLabel(e),
            e.target ?? '', detailLabel(e), ...(isAdmin ? [e.ip ?? '', e.device ?? ''] : []),
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
        toast('admin.registrants.toast.exportFailed', {}, 'danger');
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
                :disabled="exporting || !entries.total"
                @click="exportExcel"
            >
                <Fa icon="file-excel" /> {{ t('admin.activityLog.export', { count: formatNumber(entries.total) }) }}
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
            <button type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20" @click="reset">
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
                    <tbody :key="`${entries.current_page}-${entries.total}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="e in rows" :key="e.id" :class="e.flagged ? 'bg-red-50/70 dark:bg-red-500/5' : 'odd:bg-neutral-50 dark:odd:bg-white/2'">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div>{{ timeAgo(e.at) }}</div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(e.at, { dateStyle: 'medium', timeStyle: 'short' }) }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium">{{ e.actor.name ?? t('admin.activityLog.system') }}</div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ roleLabel(e.actor.role) }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center gap-2 whitespace-nowrap">
                                    <Fa :icon="categoryIcons[e.category]" :class="e.flagged ? 'text-red-500' : 'text-gold-500'" class="w-4" />
                                    {{ actionLabel(e) }}
                                    <Fa v-if="e.flagged" icon="flag" class="text-xs text-red-500" :title="t('admin.activityLog.flagged')" />
                                </span>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs break-all">
                                <Link v-if="e.submissionId" :href="`/admin/submissions/${e.submissionId}`" class="text-gold-700 hover:underline dark:text-gold-400">{{ e.target }}</Link>
                                <template v-else>{{ e.target ?? '—' }}</template>
                            </td>
                            <td class="px-3 py-2.5 text-neutral-600 dark:text-neutral-300">{{ detailLabel(e) }}</td>
                            <td v-if="isAdmin" class="px-3 py-2.5 whitespace-nowrap">
                                <div class="font-mono text-xs">{{ e.ip ?? '—' }}</div>
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
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.activityLog.count', { count: formatNumber(entries.total) }) }}</p>
            <Pagination v-model="page" :last-page="lastPage" />
        </div>
    </Card>
</template>
