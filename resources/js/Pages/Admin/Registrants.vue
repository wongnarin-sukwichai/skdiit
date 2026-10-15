<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import RowMenu from '@/Components/Admin/RowMenu.vue';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import OneTimePasswordModal from '@/Components/Admin/OneTimePasswordModal.vue';
import Pagination from '@/Components/Pagination.vue';
import CheckMark from '@/Components/CheckMark.vue';
import RegistrantStatus from '@/Components/Admin/Registrants/RegistrantStatus.vue';
import RegistrantDetailModal from '@/Components/Admin/Registrants/RegistrantDetailModal.vue';
import RegistrantFormModal from '@/Components/Admin/Registrants/RegistrantFormModal.vue';
import { useI18n } from '@/i18n';
import { useRegistrantTable } from '@/composables/useRegistrantTable';
import { useModules } from '@/composables/useModules';
import { useToast } from '@/composables/useToast';
import { adminRegistrants, affiliationByKey } from '@/data/mock';

const { t, tr, formatDate, formatNumber } = useI18n();
const { toast } = useToast();

// Mock phase: a reactive copy of the sample list, so add/edit/suspend/delete show up until the page reloads
const list = reactive(adminRegistrants.map((r) => ({ ...r })));

// suspended | unverified (never signed in with the emailed password) | active
const accountState = (row) => (!row.active ? 'suspended' : row.verified ? 'active' : 'unverified');

// YYYY-MM-DD in the app timezone, for the "registered from/to" filters
const day = (iso) => new Date(iso).toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });

const { accessTo } = useModules();
const canEdit = computed(() => accessTo('registrants') === 'full');
// Editors may look but not take the list away (personal data); only admin can export
const canExport = canEdit;

const { form, perPage, page, total, lastPage, rows, filtered, search, reset } = useRegistrantTable(list, {
    withEmail: true,
    filters: { participation: '', status: '', account: '', from: '', to: '' },
    match: (row, f) => (!f.participation || row.participation === f.participation)
        && (!f.status || (f.status === 'registered' && row.participation === 'attend')
            || (f.status === 'none' && row.participation === 'attend_submit' && !row.paperStatus)
            || row.paperStatus === f.status)
        && (!f.account || accountState(row) === f.account)
        && (!f.from || day(row.registeredAt) >= f.from)
        && (!f.to || day(row.registeredAt) <= f.to),
});

// Opened from the home page with a filter, e.g. ?account=unverified (registrants who never signed in)
const initialAccount = new URLSearchParams(window.location.search).get('account');
if (['active', 'unverified', 'suspended'].includes(initialAccount)) {
    form.account = initialAccount;
    search();
}

// Status filter: attend-only, author without a paper, then each paper status (App\Enums\SubmissionStatus)
const paperStatuses = [
    'submitted', 'journal_selected', 'journal_accepted',
    'screening', 'screening_revision', 'screening_rejected', 'in_review', 'revision_minor', 'revision_major', 'revised',
    'accepted', 'rejected', 'camera_ready',
];

const stats = computed(() => ({
    total: list.length,
    attendOnly: list.filter((r) => r.participation === 'attend').length,
    authors: list.filter((r) => r.participation === 'attend_submit').length,
    papers: list.filter((r) => r.paperStatus).length,
}));
const statCards = computed(() => [
    { key: 'total', icon: 'user' },
    { key: 'attendOnly', icon: 'users' },
    { key: 'authors', icon: 'file-arrow-up' },
    { key: 'papers', icon: 'file-lines' },
]);

// Row selection (export the selected rows)
const checked = ref(new Set());
const allChecked = computed(() => rows.value.length > 0 && rows.value.every((r) => checked.value.has(r.id)));
function toggleAll() {
    const next = new Set(checked.value);
    const all = allChecked.value;
    rows.value.forEach((r) => (all ? next.delete(r.id) : next.add(r.id)));
    checked.value = next;
}
function toggle(id) {
    const next = new Set(checked.value);
    next.has(id) ? next.delete(id) : next.add(id);
    checked.value = next;
}
watch(filtered, () => (checked.value = new Set([...checked.value].filter((id) => filtered.value.some((r) => r.id === id)))));

const find = (id) => list.find((r) => r.id === id);

// ---- Details, add and edit ----
const viewing = ref(null);
const editor = reactive({ show: false, registrant: null });

const emailTaken = (email, exceptId) => list.some((r) => r.email.toLowerCase() === email && r.id !== exceptId);

function openEditor(registrant = null) {
    Object.assign(editor, { show: true, registrant });
}

function save(data) {
    const name = { en: data.name, th: data.name };
    if (editor.registrant) {
        const registrant = find(editor.registrant.id);
        const switched = registrant.participation !== data.participation;
        Object.assign(registrant, { name, email: data.email, affiliation: data.affiliation, participation: data.participation });
        // Real system: a notification email (the registrant keeps their password)
        toast(switched ? 'admin.registrants.toast.participationChanged' : 'admin.registrants.toast.updated', { email: data.email });
    } else {
        const registrant = {
            id: Math.max(0, ...list.map((r) => r.id)) + 1,
            name,
            email: data.email,
            affiliation: data.affiliation,
            participation: data.participation,
            paper: null,
            paperStatus: null,
            active: true,
            verified: false,
            registeredAt: new Date().toISOString(),
        };
        list.unshift(registrant);
        // Real system: a generated 6-digit password, emailed (App\Mail\LoginPassword)
        toast('admin.registrants.toast.created', { email: data.email });
    }
    editor.show = false;
}

// ---- Row actions (confirmed first) ----
const pending = ref(null); // { action, registrant }
const generated = ref(null); // { name, email, password } for OneTimePasswordModal
const shownPending = ref(null); // keeps the text while the modal fades out
watch(pending, (value) => value && (shownPending.value = value));

const confirmIcons = { sendPassword: 'paper-plane', generatePassword: 'key', makeReviewer: 'user-check', suspend: 'ban', unsuspend: 'lock-open' };

function menuItems(row) {
    return [
        { key: 'edit', label: t('admin.registrants.edit'), icon: 'pen' },
        { key: 'sendPassword', label: t('admin.registrants.action.sendPassword'), icon: 'paper-plane' },
        { key: 'generatePassword', label: t('admin.oneTimePassword.action'), icon: 'key' },
        {
            key: 'makeReviewer',
            label: t('admin.registrants.action.makeReviewer'),
            icon: 'user-check',
            // One role per account; a reviewer must not have papers of their own
            disabled: !!row.paperStatus,
            title: row.paperStatus ? t('admin.registrants.makeReviewerBlocked') : undefined,
        },
        // Registrants are never deleted, only suspended (their records stay for the conference history)
        row.active
            ? { key: 'suspend', label: t('admin.registrants.action.suspend'), icon: 'ban' }
            : { key: 'unsuspend', label: t('admin.registrants.action.unsuspend'), icon: 'lock-open' },
    ];
}

function onMenu(row, action) {
    const registrant = find(row.id);
    action === 'edit' ? openEditor(registrant) : (pending.value = { action, registrant });
}

function confirmAction() {
    const { action, registrant } = pending.value;
    pending.value = null;

    if (action === 'generatePassword') {
        // Real system: POST like Settings (SettingsController::generatePassword); shown once, not emailed
        const [n] = crypto.getRandomValues(new Uint32Array(1));
        generated.value = { name: tr(registrant.name), email: registrant.email, password: String(n % 1_000_000).padStart(6, '0') };
    } else if (action === 'sendPassword') {
        // Real system: a new 6-digit password, emailed; nobody sees it on screen
        toast('admin.registrants.toast.passwordSent', { email: registrant.email });
    } else if (action === 'makeReviewer') {
        // Real system: role becomes 'reviewer' and the account moves to the Reviewers page (no tracks yet)
        list.splice(list.indexOf(registrant), 1);
        toast('admin.registrants.toast.madeReviewer', { name: tr(registrant.name) });
    } else if (action === 'suspend' || action === 'unsuspend') {
        registrant.active = action === 'unsuspend';
        toast(`admin.registrants.toast.${action}`, { name: tr(registrant.name) });
    }
}

// ---- Export to Excel (selected rows, or every row that matches the filters) ----
const exporting = ref(false);

function statusText(row) {
    if (row.paperStatus) return t(`submissions.status.${row.paperStatus}`);
    return t(row.participation === 'attend' ? 'admin.registrants.registered' : 'admin.registrants.notSubmitted');
}

async function exportExcel() {
    const source = checked.value.size ? filtered.value.filter((r) => checked.value.has(r.id)) : filtered.value;
    const headers = [
        t('registrants.no'), t('registrants.name'), t('registrants.email'), t('registrants.affiliation'),
        t('registrants.participation'), t('registrants.paper'),
        t('registrants.status'), t('admin.registrants.account'),
        t('registrants.registeredAt'),
    ];
    const body = source.map((r, i) => [
        String(i + 1),
        tr(r.name),
        r.email,
        tr(affiliationByKey[r.affiliation]),
        t(`admin.registrants.participationType.${r.participation}`),
        r.paper ? tr(r.paper) : '',
        statusText(r),
        t(`admin.registrants.${accountState(r)}`),
        formatDate(r.registeredAt, { dateStyle: 'medium', timeStyle: 'short' }),
    ]);

    exporting.value = true;
    try {
        const xsrf = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        const response = await fetch('/admin/export', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ headers, rows: body }),
        });
        if (!response.ok) throw new Error(response.statusText);

        const name = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? 'registrants.xlsx';
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

const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 py-2.5 pr-3 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
const plainBadge = 'inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap';
</script>

<template>
    <Head :title="t('admin.registrants.title')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="users" :title="t('admin.registrants.title')" :subtitle="t('admin.registrants.subtitle')" />
            <StatCards :stats="stats" :cards="statCards" />
        </Card>

        <Card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Fa icon="magnifying-glass" class="text-gold-400" /> {{ t('admin.registrants.searchTitle') }}
                </h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canExport"
                        class="flex items-center gap-2 rounded-lg border border-emerald-600/40 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-400/40 dark:text-emerald-300 dark:hover:bg-emerald-400/10"
                        :disabled="exporting || !total"
                        @click="exportExcel"
                    >
                        <Fa icon="file-excel" />
                        {{ checked.size ? t('admin.registrants.exportSelected', { count: formatNumber(checked.size) }) : t('admin.registrants.export') }}
                    </button>
                    <button v-if="canEdit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="openEditor()">
                        <Fa icon="plus" /> {{ t('admin.registrants.add') }}
                    </button>
                </div>
            </div>

            <form class="mb-5 space-y-3" @submit.prevent="search">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="relative">
                        <Fa icon="user" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                        <input v-model="form.name" :placeholder="t('admin.registrants.searchName')" :class="[input, 'pl-11']">
                    </label>
                    <label class="relative">
                        <Fa icon="building-columns" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                        <input v-model="form.affiliation" :placeholder="t('registrants.searchAffiliation')" :class="[input, 'pl-11']">
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto_auto]">
                    <select v-model="form.participation" :class="[input, 'pl-3']" :aria-label="t('registrants.participation')">
                        <option value="">{{ t('admin.registrants.allParticipation') }}</option>
                        <option value="attend">{{ t('admin.registrants.participationType.attend') }}</option>
                        <option value="attend_submit">{{ t('admin.registrants.participationType.attend_submit') }}</option>
                    </select>
                    <select v-model="form.status" :class="[input, 'pl-3']" :aria-label="t('registrants.status')">
                        <option value="">{{ t('admin.registrants.allStatuses') }}</option>
                        <option value="registered">{{ t('admin.registrants.registered') }}</option>
                        <option value="none">{{ t('admin.registrants.notSubmitted') }}</option>
                        <option v-for="s in paperStatuses" :key="s" :value="s">{{ t(`submissions.status.${s}`) }}</option>
                    </select>
                    <select v-model="form.account" :class="[input, 'pl-3']" :aria-label="t('admin.registrants.account')">
                        <option value="">{{ t('admin.registrants.allAccounts') }}</option>
                        <option value="active">{{ t('admin.registrants.active') }}</option>
                        <option value="unverified">{{ t('admin.registrants.unverified') }}</option>
                        <option value="suspended">{{ t('admin.registrants.suspended') }}</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                        <span class="shrink-0">{{ t('admin.registrants.from') }}</span>
                        <input v-model="form.from" type="date" :max="form.to || undefined" :class="[input, 'pl-3']">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                        <span class="shrink-0">{{ t('admin.registrants.to') }}</span>
                        <input v-model="form.to" type="date" :min="form.from || undefined" :class="[input, 'pl-3']">
                    </label>
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-6 py-2.5 transition hover:border-gold-400 dark:border-white/20" @click="reset">
                        <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
                    </button>
                    <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-8 py-2.5 font-semibold text-black transition hover:bg-gold-300">
                        <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <table class="w-full min-w-275 text-sm">
                    <thead class="bg-gold-300 text-left text-black dark:bg-gold-400">
                        <tr>
                            <th class="w-12 px-4 py-3">
                                <input type="checkbox" class="size-4 accent-black" :checked="allChecked" :aria-label="t('admin.registrants.selectPage')" @change="toggleAll">
                            </th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.no') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.name') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.affiliation') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.attend') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.submit') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.status') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.registeredAt') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.actions') }}</th>
                        </tr>
                    </thead>
                    <Transition name="fade" mode="out-in">
                        <tbody :key="`${page}-${perPage}-${total}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                :class="checked.has(row.id) ? 'bg-gold-50 dark:bg-gold-400/10' : 'odd:bg-neutral-50 dark:odd:bg-white/2'"
                                class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5"
                            >
                                <td class="px-4 py-2.5">
                                    <input type="checkbox" class="size-4 accent-gold-400" :checked="checked.has(row.id)" @change="toggle(row.id)">
                                </td>
                                <td class="px-3 py-2.5 text-neutral-500 dark:text-neutral-400">{{ row.no }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2 whitespace-nowrap">
                                        {{ tr(row.name) }}
                                        <span v-if="!row.active" :class="plainBadge" class="border-red-200 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-300">
                                            <Fa icon="ban" class="mr-1" /> {{ t('admin.registrants.suspended') }}
                                        </span>
                                        <span v-else-if="!row.verified" :class="plainBadge" class="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300" :title="t('admin.registrants.unverifiedHint')">
                                            <Fa icon="envelope" class="mr-1" /> {{ t('admin.registrants.unverified') }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ row.email }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-neutral-600 dark:text-neutral-300">{{ tr(affiliationByKey[row.affiliation]) }}</td>
                                <!-- Every registrant attends; "submit" = chose attend + submit at registration -->
                                <td class="px-3 py-2.5 text-center"><CheckMark checked /></td>
                                <td class="px-3 py-2.5 text-center"><CheckMark :checked="row.participation === 'attend_submit'" /></td>
                                <td class="px-3 py-2.5 text-center">
                                    <RegistrantStatus :registrant="row" />
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ formatDate(row.registeredAt) }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <button :class="iconBtn" :title="t('registrants.view')" @click="viewing = find(row.id)"><Fa icon="eye" class="text-xs" /></button>
                                        <button v-if="canEdit" :class="iconBtn" :title="t('admin.registrants.edit')" @click="openEditor(find(row.id))"><Fa icon="pen" class="text-xs" /></button>
                                        <RowMenu
                                            v-if="canEdit"
                                            :class="[iconBtn, 'border-transparent dark:border-transparent']"
                                            :label="t('admin.registrants.more')"
                                            :items="menuItems(row)"
                                            @select="onMenu(row, $event)"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="9"class="px-4 py-12 text-center text-neutral-500">{{ t('registrants.empty') }}</td>
                            </tr>
                        </tbody>
                    </Transition>
                </table>
            </div>

            <div class="mt-5 flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex flex-wrap items-center gap-3 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ t('registrants.show') }}
                    <select v-model.number="perPage" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-neutral-800 outline-none focus:border-gold-400 dark:border-white/15 dark:bg-ink-800 dark:text-white">
                        <option v-for="n in [10, 25, 50]" :key="n" :value="n">{{ n }}</option>
                    </select>
                    {{ t('registrants.ofTotal', { total: formatNumber(total) }) }}
                    <Transition name="fade">
                        <span v-if="checked.size" class="flex items-center gap-2 text-gold-600 dark:text-gold-400">
                            · {{ t('admin.registrants.selected', { count: formatNumber(checked.size) }) }}
                            <button type="button" class="underline underline-offset-2 hover:text-gold-500" @click="checked = new Set()">{{ t('admin.registrants.clearSelection') }}</button>
                        </span>
                    </Transition>
                </div>
                <Pagination v-model="page" :last-page="lastPage" />
            </div>

            <p class="mt-4 text-xs text-neutral-500 dark:text-neutral-400">
                <Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.registrants.mockNote') }}
            </p>
        </Card>
    </div>

    <RegistrantDetailModal :registrant="viewing" @close="viewing = null" />
    <RegistrantFormModal :show="editor.show" :registrant="editor.registrant" :email-taken="emailTaken" @close="editor.show = false" @save="save" />
    <OneTimePasswordModal :result="generated" @close="generated = null" />
    <ConfirmModal
        :show="!!pending"
        :icon="confirmIcons[shownPending?.action]"
        :danger="shownPending?.action === 'suspend'"
        :title="shownPending ? t(shownPending.action === 'generatePassword' ? 'admin.oneTimePassword.confirmTitle' : `admin.registrants.confirm.${shownPending.action}.title`) : ''"
        :message="shownPending ? (shownPending.action === 'generatePassword'
            ? t('admin.oneTimePassword.confirmText', { name: tr(shownPending.registrant.name) })
            : t(`admin.registrants.confirm.${shownPending.action}.message`, { name: tr(shownPending.registrant.name), email: shownPending.registrant.email })) : ''"
        :confirm-label="shownPending ? (shownPending.action === 'generatePassword' ? t('admin.oneTimePassword.action') : t(`admin.registrants.action.${shownPending.action}`)) : ''"
        @close="pending = null"
        @confirm="confirmAction"
    />
</template>
