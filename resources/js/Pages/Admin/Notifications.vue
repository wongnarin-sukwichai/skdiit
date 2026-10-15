<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import EmailPreviewModal from '@/Components/Admin/EmailPreviewModal.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { timeAgo, useNotifications } from '@/composables/useNotifications';
import { emailLog } from '@/data/mock';

/*
 * Notifications (every role): what happened to my papers and reviews.
 * Admin also sees the email log (every email the system sent). Mock until SMTP is decided.
 */
const { t, tr, formatDate, formatNumber } = useI18n();
const { toast } = useToast();
const { items, unread, markRead, markAllRead } = useNotifications();
const me = usePage().props.auth.user;
const isAdmin = computed(() => me.accessRole === 'admin');
// Editors coordinate with authors and reviewers: they see paper emails (not passwords)
const seesEmails = computed(() => ['admin', 'editor'].includes(me.accessRole));
const PAPER_ONLY_HIDDEN = ['LoginPassword'];

const tab = ref(seesEmails.value && new URLSearchParams(usePage().url.split('?')[1]).get('tab') === 'emails' ? 'emails' : 'notifications');

// ---- Notifications ----
const filter = ref('all');
const filters = computed(() => ['all', 'unread', ...new Set(items.value.map((n) => n.type))]);
const shown = computed(() => items.value.filter((n) => filter.value === 'all' || (filter.value === 'unread' ? !n.read : n.type === filter.value)));
const countFor = (key) => (key === 'all' ? items.value.length : key === 'unread' ? unread.value : items.value.filter((n) => n.type === key).length);

function open(item) {
    markRead(item);
    router.visit(item.href);
}

// ---- Email log (admin) ----
const emails = reactive(emailLog
    .filter((e) => isAdmin.value || !PAPER_ONLY_HIDDEN.includes(e.template))
    .map((e) => ({ ...e, resentBy: null, resentAt: null })));
const emailFilter = reactive({ q: '', status: '' });
const emailPage = ref(1);
const emailRows = computed(() => {
    const q = emailFilter.q.trim().toLowerCase();
    return emails.filter((e) => (!emailFilter.status || e.status === emailFilter.status)
        && (!q || `${e.to} ${e.name.en} ${e.name.th} ${e.subject.en} ${e.subject.th}`.toLowerCase().includes(q)));
});
const emailLastPage = computed(() => Math.max(1, Math.ceil(emailRows.value.length / 10)));
const emailPageRows = computed(() => emailRows.value.slice((emailPage.value - 1) * 10, emailPage.value * 10));
const failedCount = computed(() => emails.filter((e) => e.status === 'failed').length);

// Open the email as sent, and send it again (e.g. the user says it never arrived); always confirmed first
const previewing = ref(null);
const resending = ref(null);
const shownResending = ref(null);
function askResend(email) {
    previewing.value = null;
    resending.value = email;
    shownResending.value = email;
}
function resend() {
    // Real system: queue the same Mailable again (LoginPassword creates a new password, the old one is never stored)
    const email = resending.value;
    // Who resent it, so other editors don't send it a third time
    Object.assign(email, { status: 'queued', error: null, resent: email.resent + 1, resentBy: me.name, resentAt: new Date().toISOString() });
    toast('admin.notificationsPage.toast.resent', { email: email.to });
    resending.value = null;
}

const statusStyle = {
    sent: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300',
    failed: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-300',
    queued: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-300',
};
const input = 'rounded-lg border border-neutral-300 bg-neutral-50 py-2.5 pr-3 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
</script>

<template>
    <Head :title="t('admin.menu.notifications')" />

    <Card class="space-y-6">
        <PageHeader icon="bell" :title="t('admin.menu.notifications')" :subtitle="t('admin.notificationsPage.subtitle')" />

        <nav v-if="seesEmails" class="flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-white/10" role="tablist">
            <button
                v-for="key in ['notifications', 'emails']"
                :key="key"
                role="tab"
                :aria-selected="tab === key"
                :class="tab === key
                    ? 'border-gold-400 text-gold-600 dark:text-gold-400'
                    : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                @click="tab = key"
            >
                <Fa :icon="key === 'emails' ? 'envelope' : 'bell'" /> {{ t(`admin.notificationsPage.tabs.${key}`) }}
                <span v-if="key === 'emails' && failedCount" class="rounded-full bg-red-500 px-2 py-0.5 text-xs text-white">{{ formatNumber(failedCount) }}</span>
            </button>
        </nav>

        <Transition name="fade" mode="out-in">
            <!-- My notifications -->
            <div v-if="tab === 'notifications'" key="notifications" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="key in filters"
                            :key="key"
                            :class="filter === key
                                ? 'border-gold-400 bg-gold-400 text-black'
                                : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                            class="rounded-full border px-3.5 py-1.5 text-sm transition"
                            @click="filter = key"
                        >
                            {{ t(`admin.notificationsPage.filter.${key}`) }} ({{ formatNumber(countFor(key)) }})
                        </button>
                    </div>
                    <button class="flex items-center gap-2 text-sm text-gold-600 transition hover:underline disabled:opacity-40 disabled:hover:no-underline dark:text-gold-400" :disabled="!unread" @click="markAllRead">
                        <Fa icon="check" /> {{ t('admin.notificationsPage.markAll') }}
                    </button>
                </div>

                <TransitionGroup name="fade" tag="ul" class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                    <li v-for="item in shown" :key="item.id">
                        <button
                            :class="item.read ? '' : 'bg-gold-50/60 dark:bg-gold-400/5'"
                            class="flex w-full gap-4 p-4 text-left transition hover:bg-gold-50 dark:hover:bg-gold-400/10"
                            @click="open(item)"
                        >
                            <span :class="item.read ? 'bg-neutral-100 text-neutral-500 dark:bg-white/5 dark:text-neutral-400' : 'bg-gold-100 text-gold-600 dark:bg-gold-400/15 dark:text-gold-400'" class="grid size-11 shrink-0 place-items-center rounded-full">
                                <Fa :icon="item.icon" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline justify-between gap-x-3">
                                    <span :class="item.read ? 'font-medium' : 'font-semibold'">{{ tr(item.title) }}</span>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400" :title="formatDate(item.at, { dateStyle: 'medium', timeStyle: 'short' })">{{ timeAgo(item.at) }}</span>
                                </span>
                                <span class="mt-0.5 block text-sm text-neutral-600 dark:text-neutral-300">{{ tr(item.body) }}</span>
                            </span>
                            <span v-if="!item.read" class="mt-2 size-2.5 shrink-0 rounded-full bg-gold-400" :title="t('admin.notificationsPage.filter.unread')" />
                        </button>
                    </li>
                    <li v-if="!shown.length" key="empty" class="flex flex-col items-center px-4 py-14 text-center text-neutral-500">
                        <Fa icon="bell" class="mb-3 text-3xl text-neutral-300 dark:text-neutral-600" />
                        {{ t('admin.notificationsPage.empty') }}
                    </li>
                </TransitionGroup>
            </div>

            <!-- Email log (admin) -->
            <div v-else key="emails" class="space-y-4">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.notificationsPage.emailsHint') }}</p>
                <div class="flex flex-wrap gap-3">
                    <label class="relative min-w-60 flex-1">
                        <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                        <input v-model="emailFilter.q" :placeholder="t('admin.notificationsPage.emailSearch')" :class="[input, 'w-full pl-11']" @input="emailPage = 1">
                    </label>
                    <select v-model="emailFilter.status" :class="[input, 'pl-3']" :aria-label="t('registrants.status')" @change="emailPage = 1">
                        <option value="">{{ t('admin.registrants.allStatuses') }}</option>
                        <option v-for="s in ['sent', 'failed', 'queued']" :key="s" :value="s">{{ t(`admin.notificationsPage.emailStatus.${s}`) }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                    <table class="w-full min-w-250 text-sm">
                        <thead class="bg-gold-300 text-left text-black dark:bg-gold-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">{{ t('admin.notificationsPage.sentAt') }}</th>
                                <th class="px-3 py-3 font-semibold">{{ t('admin.notificationsPage.to') }}</th>
                                <th class="px-3 py-3 font-semibold">{{ t('admin.notificationsPage.subject') }}</th>
                                <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.status') }}</th>
                                <th class="px-3 py-3 text-center font-semibold">{{ t('admin.notificationsPage.email') }}</th>
                                <th class="px-3 py-3 text-center font-semibold">{{ t('admin.notificationsPage.resend') }}</th>
                            </tr>
                        </thead>
                        <Transition name="fade" mode="out-in">
                            <tbody :key="`${emailPage}-${emailRows.length}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                                <tr v-for="email in emailPageRows" :key="email.id" class="odd:bg-neutral-50 dark:odd:bg-white/2">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ formatDate(email.at, { dateStyle: 'medium', timeStyle: 'short' }) }}</td>
                                    <td class="px-3 py-2.5">
                                        <div>{{ tr(email.name) }}</div>
                                        <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ email.to }}</div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div>{{ tr(email.subject) }}</div>
                                        <div class="font-mono text-xs text-neutral-400">{{ email.template }}</div>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span :class="statusStyle[email.status]" class="inline-flex rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap">{{ t(`admin.notificationsPage.emailStatus.${email.status}`) }}</span>
                                        <div v-if="email.error" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ email.error }}</div>
                                        <div v-if="email.resent" class="mt-1 text-xs text-sky-600 dark:text-sky-400">
                                            {{ t('admin.notificationsPage.resentTimes', { n: email.resent }) }}
                                            <template v-if="email.resentBy"><br>{{ t('admin.notificationsPage.resentBy', { name: email.resentBy, when: timeAgo(email.resentAt) }) }}</template>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <button class="inline-flex items-center gap-1.5 text-xs font-medium whitespace-nowrap text-gold-700 underline-offset-2 hover:underline dark:text-gold-400" @click="previewing = email">
                                            <Fa icon="envelope-open-text" /> {{ t('admin.notificationsPage.open') }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <button
                                            :class="email.status === 'failed'
                                                ? 'border-red-400 text-red-600 hover:bg-red-50 dark:border-red-500/50 dark:text-red-300 dark:hover:bg-red-500/10'
                                                : 'border-neutral-300 hover:border-gold-400 hover:text-gold-600 dark:border-white/15 dark:hover:text-gold-400'"
                                            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition disabled:opacity-40"
                                            :disabled="email.status === 'queued'"
                                            :title="email.status === 'queued' ? t('admin.notificationsPage.emailStatus.queued') : undefined"
                                            @click="askResend(email)"
                                        >
                                            <Fa icon="paper-plane" /> {{ t('admin.notificationsPage.resendShort') }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!emailPageRows.length">
                                    <td colspan="6" class="px-4 py-12 text-center text-neutral-500">{{ t('admin.notificationsPage.emailEmpty') }}</td>
                                </tr>
                            </tbody>
                        </Transition>
                    </table>
                </div>
                <div class="flex justify-end">
                    <Pagination v-model="emailPage" :last-page="emailLastPage" />
                </div>
            </div>
        </Transition>

        <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.notificationsPage.mockNote') }}</p>
    </Card>

    <EmailPreviewModal :email="previewing" @close="previewing = null" @resend="askResend" />
    <ConfirmModal
        :show="!!resending"
        icon="paper-plane"
        :title="t('admin.notificationsPage.confirmResendTitle')"
        :message="shownResending ? t(shownResending.template === 'LoginPassword' ? 'admin.notificationsPage.confirmResendPassword' : 'admin.notificationsPage.confirmResend', { subject: tr(shownResending.subject), email: shownResending.to }) : ''"
        :confirm-label="t('admin.notificationsPage.resend')"
        @close="resending = null"
        @confirm="resend"
    />
</template>
