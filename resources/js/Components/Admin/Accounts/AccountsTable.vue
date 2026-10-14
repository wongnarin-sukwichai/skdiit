<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import RoleBadge from '@/Components/Admin/RoleBadge.vue';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import OneTimePasswordModal from '@/Components/Admin/OneTimePasswordModal.vue';
import AccountFormModal from '@/Components/Admin/Accounts/AccountFormModal.vue';
import AccountDetailModal from '@/Components/Admin/Accounts/AccountDetailModal.vue';
import RowMenu from '@/Components/Admin/RowMenu.vue';
import { useI18n } from '@/i18n';
import { useAcademicTitles } from '@/composables/useAcademicTitles';

/**
 * Account list for the Staff page (admin, editor, finance) and the Reviewers page.
 * baseUrl serves index, store, update, destroy and {id}/password (Admin\Concerns\ManagesAccounts).
 */
const props = defineProps({
    accounts: { type: Object, required: true }, // Laravel paginator
    filters: { type: Object, required: true },
    roles: { type: Array, required: true }, // more than one = role filter, column and picker
    baseUrl: { type: String, required: true },
    tracks: { type: Array, default: null }, // reviewers: track filter and badges
    canEdit: { type: Boolean, default: true },
    addLabel: { type: String, required: true },
});

const { t, tr, formatDate, formatNumber } = useI18n();
const { titleOf } = useAcademicTitles();
const inertiaPage = usePage();
const me = computed(() => inertiaPage.props.auth.user);
const multiRole = computed(() => props.roles.length > 1);

const q = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');
const track = ref(props.filters.track ?? '');

function load(params = {}) {
    router.get(props.baseUrl, {
        q: q.value || undefined,
        role: role.value || undefined,
        track: track.value || undefined,
        ...params,
    }, { only: ['accounts', 'filters'], preserveState: true, preserveScroll: true, replace: true });
}
const page = computed({
    get: () => props.accounts.current_page,
    set: (value) => load({ page: value }),
});
function reset() {
    q.value = '';
    role.value = '';
    track.value = '';
    load();
}

const trackById = computed(() => Object.fromEntries((props.tracks ?? []).map((item) => [item.id, item])));
const trackName = (item) => tr({ en: item.name_en, th: item.name_th });

// Create (null) or edit
const editing = ref(null);
const formOpen = ref(false);
function openForm(account = null) {
    editing.value = account;
    formOpen.value = true;
}

const viewing = ref(null);
const viewingTracks = computed(() => (props.tracks && viewing.value
    ? viewing.value.tracks.map((id) => trackById.value[id]).filter(Boolean).map(trackName)
    : null));

// ⋯ menu actions, each confirmed first. Delete needs a suspended account; nobody acts on their own account.
const pending = ref(null); // { action, account }
const shownPending = ref(null); // keeps the text while the modal fades out
const busy = ref(false);
const generated = ref(null); // "email didn't arrive": a password shown once, to pass on by private chat

const confirmIcons = { impersonate: 'user-secret', sendPassword: 'paper-plane', password: 'key', suspend: 'ban', unsuspend: 'lock-open', delete: 'trash-can' };

function menuItems(account) {
    const self = account.id === me.value.id;
    return [
        { key: 'edit', label: t('admin.accounts.edit'), icon: 'pen' },
        // Admin sees the system exactly as this account does (read only); not for admins or suspended accounts
        ...(me.value.accessRole === 'admin' && account.role !== 'admin'
            ? [{ key: 'impersonate', label: t('impersonation.action'), icon: 'user-secret', disabled: account.suspended, title: account.suspended ? t('impersonation.suspended') : undefined }]
            : []),
        { key: 'sendPassword', label: t('admin.registrants.action.sendPassword'), icon: 'paper-plane', disabled: self },
        { key: 'password', label: t('admin.oneTimePassword.action'), icon: 'key', disabled: self },
        account.suspended
            ? { key: 'unsuspend', label: t('admin.registrants.action.unsuspend'), icon: 'lock-open' }
            : { key: 'suspend', label: t('admin.registrants.action.suspend'), icon: 'ban', disabled: self },
        {
            key: 'delete',
            label: t('admin.accounts.delete'),
            icon: 'trash-can',
            danger: true,
            disabled: self || !account.suspended,
            title: account.suspended ? undefined : t('admin.accounts.deleteNeedsSuspend'),
        },
    ];
}

function onMenu(account, action) {
    if (action === 'edit') return openForm(account);
    pending.value = { action, account };
    shownPending.value = pending.value;
}

const confirmTitle = (p) => (p.action === 'impersonate' ? t('impersonation.confirmTitle')
    : p.action === 'password' ? t('admin.oneTimePassword.confirmTitle') : t(`admin.accounts.confirm.${p.action}.title`));
const confirmText = (p) => (p.action === 'impersonate' ? t('impersonation.confirmText', { name: p.account.name })
    : p.action === 'password'
    ? t('admin.oneTimePassword.confirmText', { name: p.account.name })
    : t(`admin.accounts.confirm.${p.action}.message`, { name: p.account.name, email: p.account.email }));
const confirmLabel = (p) => (p.action === 'impersonate' ? t('impersonation.action')
    : p.action === 'password' ? t('admin.oneTimePassword.action')
    : p.action === 'delete' ? t('admin.accounts.delete')
    : t(`admin.registrants.action.${p.action}`));

function confirm() {
    const { action, account } = pending.value;
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => {
            busy.value = false;
            pending.value = null;
        },
    };
    const url = `${props.baseUrl}/${account.id}`;
    if (action === 'impersonate') {
        router.post(`/admin/impersonate/${account.id}`, {}, options);
    } else if (action === 'delete') {
        router.delete(url, options);
    } else if (action === 'password') {
        router.post(`${url}/password`, {}, { ...options, onSuccess: () => (generated.value = inertiaPage.props.flash.generatedPassword) });
    } else {
        router.post(`${url}/${{ sendPassword: 'send-password', suspend: 'suspend', unsuspend: 'unsuspend' }[action]}`, {}, options);
    }
}

const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 disabled:opacity-40 disabled:hover:border-neutral-300 disabled:hover:text-neutral-600 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form class="grid flex-1 gap-3 sm:grid-cols-[1fr_14rem_auto_auto]" @submit.prevent="load()">
                <label class="relative">
                    <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                    <input v-model="q" :placeholder="t('admin.accounts.search')" :class="[input, 'pl-11']">
                </label>
                <select v-if="multiRole" v-model="role" :class="input" :aria-label="t('admin.accounts.role')" @change="load()">
                    <option value="">{{ t('admin.accounts.allRoles') }}</option>
                    <option v-for="r in roles" :key="r" :value="r">{{ t(`admin.role.${r}`) }}</option>
                </select>
                <select v-else-if="tracks" v-model="track" :class="input" :aria-label="t('admin.accounts.tracks')" @change="load()">
                    <option value="">{{ t('admin.accounts.allTracks') }}</option>
                    <option v-for="item in tracks" :key="item.id" :value="String(item.id)">{{ trackName(item) }}</option>
                </select>
                <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
                </button>
                <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20" @click="reset">
                    <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
                </button>
            </form>
            <button v-if="canEdit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="openForm()">
                <Fa icon="user-plus" /> {{ addLabel }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-180 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('admin.accounts.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.accounts.phone') }}</th>
                        <th v-if="multiRole" class="px-4 py-3 font-medium">{{ t('admin.accounts.role') }}</th>
                        <th v-if="tracks" class="px-4 py-3 font-medium">{{ t('admin.accounts.tracks') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.accounts.joined') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('registrants.actions') }}</th>
                    </tr>
                </thead>
                <Transition name="fade" mode="out-in">
                    <tbody :key="`${accounts.current_page}-${accounts.total}-${filters.q}-${filters.role}-${filters.track}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="account in accounts.data" :key="account.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                            <td class="px-4 py-3">
                                <span class="block font-medium">
                                    <span v-if="account.academic_title_id" class="text-neutral-500 dark:text-neutral-400">{{ titleOf(account.academic_title_id) }}</span>
                                    {{ account.name }}
                                    <span v-if="account.id === me.id" class="ml-1 text-xs text-gold-600 dark:text-gold-400">({{ t('admin.accounts.you') }})</span>
                                    <span v-if="account.suspended" class="ml-1 inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-300">
                                        <Fa icon="ban" class="mr-1" /> {{ t('admin.registrants.suspended') }}
                                    </span>
                                </span>
                                <span class="block text-neutral-500 dark:text-neutral-400">{{ account.email }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ account.phone || '—' }}</td>
                            <td v-if="multiRole" class="px-4 py-3"><RoleBadge :role="account.role" /></td>
                            <td v-if="tracks" class="px-4 py-3">
                                <div v-if="account.tracks.length" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="id in account.tracks"
                                        :key="id"
                                        class="rounded-full border border-gold-300 bg-gold-50 px-2.5 py-0.5 text-xs text-gold-700 dark:border-gold-400/40 dark:bg-gold-400/10 dark:text-gold-300"
                                    >
                                        {{ trackById[id] ? trackName(trackById[id]) : id }}
                                    </span>
                                </div>
                                <span v-else class="text-xs text-red-600 dark:text-red-400">
                                    <Fa icon="triangle-exclamation" class="mr-1" /> {{ t('admin.accounts.noTrack') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ account.createdAt ? formatDate(account.createdAt) : '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <button :class="iconBtn" :title="t('registrants.view')" @click="viewing = account"><Fa icon="eye" class="text-xs" /></button>
                                    <button v-if="canEdit" :class="iconBtn" :title="t('admin.accounts.edit')" @click="openForm(account)"><Fa icon="pen" class="text-xs" /></button>
                                    <RowMenu
                                        v-if="canEdit"
                                        :class="[iconBtn, 'border-transparent dark:border-transparent']"
                                        :label="t('admin.registrants.more')"
                                        :items="menuItems(account)"
                                        @select="onMenu(account, $event)"
                                    />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!accounts.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">{{ t('admin.accounts.empty') }}</td>
                        </tr>
                    </tbody>
                </Transition>
            </table>
        </div>

        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.accounts.total', { total: formatNumber(accounts.total) }) }}</p>
            <Pagination v-if="accounts.last_page > 1" v-model="page" :last-page="accounts.last_page" />
        </div>

        <AccountFormModal :show="formOpen" :account="editing" :roles="roles" :base-url="baseUrl" @close="formOpen = false" />
        <AccountDetailModal :account="viewing" :track-names="viewingTracks" @close="viewing = null" />
        <ConfirmModal
            :show="!!pending"
            :icon="confirmIcons[shownPending?.action]"
            :danger="['delete', 'suspend'].includes(shownPending?.action)"
            :title="shownPending ? confirmTitle(shownPending) : ''"
            :message="shownPending ? confirmText(shownPending) : ''"
            :confirm-label="shownPending ? confirmLabel(shownPending) : ''"
            :processing="busy"
            @close="pending = null"
            @confirm="confirm"
        />
        <OneTimePasswordModal :result="generated" @close="generated = null" />
    </div>
</template>
