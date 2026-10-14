<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import SubmissionStatusBadge from '@/Components/Admin/SubmissionStatusBadge.vue';
import AuthorNextStep from '@/Components/Admin/AuthorNextStep.vue';
import TrendChart from '@/Components/Admin/Charts/TrendChart.vue';
import { useI18n } from '@/i18n';
import { timeAgo, useNotifications } from '@/composables/useNotifications';
import { eventIcons, eventTitle } from '@/utils/submissionEvents';
import { duplicateSlips, overdueDays } from '@/utils/payments';
import { adminPayments, dashboardData, emailLog, importantDates, paymentFees, reportData } from '@/data/mock';

// Editors are the coordinators (no secretariat role): they also follow registrants and paper emails.
// Sample counts until those modules are real; paper emails = everything except passwords and payments.
const paperEmailsFailed = emailLog.filter((e) => e.status === 'failed' && !['LoginPassword', 'PaymentResult'].includes(e.template)).length;

/*
 * Home: "what do I have to do now", per role (mock). Totals and breakdowns live in Reports.
 */
const props = defineProps({
    // Real paper numbers for editor and admin (DashboardController): { queues, journalWaiting, overdueReviews, overdue }
    papers: { type: Object, default: null },
    // Real work of a reviewer (DashboardController::reviewerWork): { kpis, reviews }
    mine: { type: Object, default: null },
    // An author's own papers with the next step (DashboardController::authorPapers), their turn first
    myPapers: { type: Array, default: null },
});

const { t, tr, formatDate, formatNumber } = useI18n();
const user = computed(() => usePage().props.auth.user);
const role = computed(() => user.value.accessRole);

// Mock data per role, with the paper parts replaced by real numbers where they exist
const queueLink = (queue) => `/admin/submissions?queue=${queue}`;
const data = computed(() => {
    const mock = dashboardData[role.value] ?? {};
    if (props.mine) return props.mine;
    // Finance: what needs handling, counted the same way as the Payments page (sample data)
    if (role.value === 'finance') {
        const payLink = (filter) => `/admin/payments?filter=${filter}`;
        const toCheck = adminPayments.filter((p) => p.status === 'pending_review').length;
        return {
            ...mock,
            kpis: { ...mock.kpis, slipsToCheck: toCheck },
            tasks: [
                { key: 'slipsToCheck', icon: 'magnifying-glass', count: toCheck, href: payLink('pending_review') },
                { key: 'duplicateSlips', icon: 'copy', count: Object.keys(duplicateSlips(adminPayments)).length, href: payLink('duplicate') },
                { key: 'overduePayments', icon: 'clock', count: adminPayments.filter((p) => overdueDays(p, paymentFees.dueDays) > 0).length, href: payLink('overdue') },
                { key: 'slipsRejected', icon: 'circle-exclamation', count: adminPayments.filter((p) => p.status === 'slip_rejected').length, href: payLink('slip_rejected') },
            ],
        };
    }
    // Author: real papers; payments are still sample data
    if (props.myPapers) return { ...mock, papers: props.myPapers };
    const p = props.papers;
    if (!p) return mock;
    if (role.value === 'editor') {
        return {
            kpis: { waitingScreening: p.queues.screening, readyToDecide: p.queues.readyToDecide, revisionsBack: p.queues.revised, overdueReviews: p.overdueReviews },
            tasks: [
                { key: 'screening', icon: 'file-lines', count: p.queues.screening, href: queueLink('screening') },
                { key: 'needReviewers', icon: 'user-check', count: p.queues.needReviewers, href: queueLink('needReviewers') },
                { key: 'readyToDecide', icon: 'gavel', count: p.queues.readyToDecide, href: queueLink('readyToDecide') },
                { key: 'revisionsBack', icon: 'rotate', count: p.queues.revised, href: queueLink('revised') },
                { key: 'overdueReviews', icon: 'triangle-exclamation', count: p.queues.overdue, href: queueLink('overdue') },
                { key: 'reviewerUnavailable', icon: 'user-xmark', count: p.queues.unavailable, href: queueLink('unavailable') },
                { key: 'journalWaiting', icon: 'paper-plane', count: p.journalWaiting, href: '/admin/publication' },
                // Coordination
                { key: 'paperEmailsFailed', icon: 'envelope', count: paperEmailsFailed, href: '/admin/notifications?tab=emails' },
                { key: 'reviewersNotSignedIn', icon: 'user-clock', count: p.reviewersNotSignedIn, href: '/admin/reviewers' },
                { key: 'registrantsToday', icon: 'user-plus', count: dashboardData.admin.kpis.registrantsToday, href: '/admin/registrants' },
                { key: 'notSignedIn', icon: 'user-clock', count: dashboardData.admin.tasks.find((x) => x.key === 'notSignedIn').count, href: '/admin/registrants?account=unverified' },
            ],
            overdue: p.overdue,
            activity: p.activity,
        };
    }
    // Admin: the paper numbers are real, everything else is still sample data
    const real = { screening: [p.queues.screening, queueLink('screening')], overdueReviews: [p.queues.overdue, queueLink('overdue')] };
    return {
        ...mock,
        kpis: { ...mock.kpis, waitingScreening: p.queues.screening, overdueReviews: p.overdueReviews },
        tasks: mock.tasks.map((task) => (real[task.key] ? { ...task, count: real[task.key][0], href: real[task.key][1] } : task)),
    };
});

const kpiIcons = {
    registrantsToday: 'user-plus', waitingScreening: 'file-lines', overdueReviews: 'triangle-exclamation', slipsToCheck: 'receipt',
    readyToDecide: 'gavel', revisionsBack: 'rotate',
    pendingReviews: 'clipboard-check', dueSoon: 'clock', overdueMine: 'triangle-exclamation', doneReviews: 'circle-check',
    receivedToday: 'circle-check', outstandingBaht: 'clock', paidCount: 'receipt',
};
const statCards = computed(() => Object.keys(data.value.kpis ?? {}).map((key) => ({ key, icon: kpiIcons[key] ?? 'gauge' })));

// Roles without the Notifications menu (reviewers, authors, finance) read their notifications here
const { items: notifications, unread, markRead, markAllRead } = useNotifications();
const hasNotificationsMenu = computed(() => usePage().props.auth.modules.some((m) => m.key === 'notifications'));

const today = formatDate(new Date().toISOString(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const recentDays = reportData.registrants.trend.slice(-14);
const recentReceipts = reportData.finance.receiptsTrend.slice(-14);
const slipsWaiting = adminPayments.filter((p) => p.status === 'pending_review').slice(0, 5);
const upcomingDates = computed(() => importantDates
    .filter((d) => d.date >= new Date().toISOString().slice(0, 10))
    .sort((a, b) => a.date.localeCompare(b.date))
    .slice(0, 4));
const daysUntil = (date) => Math.ceil((new Date(date) - new Date()) / 86400000);

const reviewState = {
    overdue: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-300',
    dueSoon: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300',
    pending: 'border-neutral-300 bg-neutral-100 text-neutral-600 dark:border-white/20 dark:bg-white/10 dark:text-neutral-300',
    unavailable: 'border-neutral-300 bg-neutral-50 text-neutral-500 line-through dark:border-white/15 dark:bg-white/5 dark:text-neutral-400',
};
const h2 = 'mb-4 flex items-center gap-2 text-lg font-semibold';
const row = 'flex items-center gap-3 rounded-lg px-3 py-2.5 transition hover:bg-gold-50 dark:hover:bg-gold-400/5';
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="gauge" :title="t('admin.dashboard.welcome', { name: user.name })" :subtitle="`${today} · ${t(`admin.dash.lead.${role}`)}`" />
            <StatCards v-if="statCards.length" :stats="data.kpis" :cards="statCards" />
        </Card>

        <!-- Admin and editor: work queues -->
        <div v-if="data.tasks" :class="role === 'finance' ? '' : 'xl:grid-cols-[1.2fr_1fr]'" class="grid gap-6">
            <Card>
                <h2 :class="h2"><Fa icon="list-check" class="text-gold-500" /> {{ t('admin.dash.todo') }}</h2>
                <ul class="space-y-1">
                    <li v-for="task in data.tasks" :key="task.key">
                        <Link :href="task.href" :class="row">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gold-100 text-gold-600 dark:bg-gold-400/15 dark:text-gold-400"><Fa :icon="task.icon" /></span>
                            <span class="flex-1 text-sm">{{ t(`admin.dash.task.${task.key}`) }}</span>
                            <span
                                :class="task.count ? 'bg-gold-400 text-black' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'"
                                class="min-w-9 rounded-full px-2.5 py-0.5 text-center text-sm font-semibold tabular-nums"
                            >{{ formatNumber(task.count) }}</span>
                            <Fa icon="chevron-right" class="text-xs text-neutral-400" />
                        </Link>
                    </li>
                </ul>
            </Card>

            <!-- Admin: latest activity and sign-ups -->
            <Card v-if="role === 'admin'" class="space-y-6">
                <div>
                    <h2 :class="h2"><Fa icon="chart-line" class="text-gold-500" /> {{ t('admin.dash.signups14') }}</h2>
                    <TrendChart :points="recentDays" :label="t('admin.dash.people')" />
                </div>
                <div>
                    <h2 :class="h2"><Fa icon="clock-rotate-left" class="text-gold-500" /> {{ t('admin.dash.activity') }}</h2>
                    <ul class="space-y-3">
                        <li v-for="(item, i) in data.activity" :key="i" class="flex gap-3 text-sm">
                            <Fa :icon="item.icon" class="mt-0.5 w-4 text-neutral-400" />
                            <span class="flex-1">{{ tr(item.text) }}</span>
                            <span class="shrink-0 text-xs text-neutral-500 dark:text-neutral-400">{{ timeAgo(item.at) }}</span>
                        </li>
                    </ul>
                </div>
            </Card>

            <!-- Editor: late reviewers, then paper activity with who did it -->
            <div v-else-if="role === 'editor'" class="space-y-6">
            <Card>
                <h2 :class="h2"><Fa icon="triangle-exclamation" class="text-red-500" /> {{ t('admin.dash.lateReviewers') }}</h2>
                <ul class="divide-y divide-neutral-200 dark:divide-white/5">
                    <li v-for="item in data.overdue" :key="`${item.code}-${item.reviewer}`">
                        <Link :href="`/admin/submissions/${item.submissionId}`" class="flex items-center gap-3 py-3 text-sm transition hover:text-gold-600 dark:hover:text-gold-400">
                            <span class="flex-1">
                                <span class="block font-medium">{{ item.reviewer }}</span>
                                <span class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ item.code }}</span>
                            </span>
                            <span class="text-right">
                                <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-500/15 dark:text-red-300">{{ t('admin.dash.daysLate', { n: item.days }) }}</span>
                                <span v-if="item.reminder" class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">
                                    <Fa icon="bell" class="mr-1" />{{ t('admin.dash.remindedBy', { name: item.reminder.by ?? '—', when: timeAgo(item.reminder.at) }) }}
                                </span>
                                <span v-else class="mt-1 block text-xs text-amber-600 dark:text-amber-400">{{ t('admin.dash.notReminded') }}</span>
                            </span>
                            <Fa icon="chevron-right" class="text-xs text-neutral-400" />
                        </Link>
                    </li>
                    <li v-if="!data.overdue.length" class="py-6 text-center text-sm text-emerald-600 dark:text-emerald-400"><Fa icon="circle-check" class="mr-1" /> {{ t('admin.dash.noLate') }}</li>
                </ul>
            </Card>
            <Card>
                <h2 :class="h2"><Fa icon="clock-rotate-left" class="text-gold-500" /> {{ t('admin.dash.paperActivity') }}</h2>
                <ul class="space-y-3">
                    <li v-for="event in data.activity" :key="event.id">
                        <Link :href="`/admin/submissions/${event.submissionId}`" class="flex gap-3 text-sm transition hover:text-gold-600 dark:hover:text-gold-400">
                            <Fa :icon="eventIcons[event.action] ?? 'circle-info'" class="mt-0.5 w-4 text-neutral-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block">{{ eventTitle(t, event) }} <span class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ event.code }}</span></span>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ event.actor ? `${event.actor.name} (${t(`admin.role.${event.actor.role}`)})` : t('admin.activityLog.system') }}
                                </span>
                            </span>
                            <span class="shrink-0 text-xs text-neutral-500 dark:text-neutral-400">{{ timeAgo(event.at) }}</span>
                        </Link>
                    </li>
                </ul>
            </Card>
            </div>
        </div>

        <!-- Author: my papers and what to do next -->
        <div v-if="role === 'author'" class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <Card>
                <h2 :class="h2"><Fa icon="file-arrow-up" class="text-gold-500" /> {{ t('admin.dash.myPapers') }}</h2>
                <ul class="space-y-3">
                    <li v-if="!data.papers.length" class="rounded-xl border border-dashed border-neutral-300 py-10 text-center text-sm text-neutral-500 dark:border-white/15">
                        {{ t('submissions.mine.empty') }}
                        <Link href="/admin/my-submissions" class="mt-2 block text-gold-600 hover:underline dark:text-gold-400">{{ t('submissions.mine.new') }}</Link>
                    </li>
                    <li v-for="paper in data.papers" :key="paper.code">
                        <Link
                            :href="`/admin/my-submissions/${paper.id}`"
                            :class="paper.next.mine ? 'border-sky-400 bg-sky-50/60 dark:bg-sky-400/10' : 'border-neutral-200 dark:border-white/10'"
                            class="block rounded-xl border p-4 transition hover:border-gold-400"
                        >
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <span class="font-mono text-xs text-gold-600 dark:text-gold-400">{{ paper.code }}</span>
                                <SubmissionStatusBadge :status="paper.status" />
                            </div>
                            <p class="font-medium">{{ tr(paper.title) }}</p>
                            <p class="mt-2"><AuthorNextStep :next="paper.next" /></p>
                        </Link>
                    </li>
                </ul>
            </Card>
            <div class="space-y-6">
                <Card v-if="data.paymentsDue">
                    <Link href="/admin/payments" class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"><Fa icon="receipt" /></span>
                        <span class="flex-1">
                            <span class="block font-semibold">{{ t('admin.dash.paymentsDue', { n: data.paymentsDue }) }}</span>
                            <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.dash.paymentsHint') }}</span>
                        </span>
                        <Fa icon="chevron-right" class="text-xs text-neutral-400" />
                    </Link>
                </Card>
                <Card>
                    <h2 :class="h2"><Fa icon="calendar-days" class="text-gold-500" /> {{ t('admin.dash.upcoming') }}</h2>
                    <ul class="space-y-3">
                        <li v-for="item in upcomingDates" :key="item.date" class="flex items-center gap-3 text-sm">
                            <span class="w-24 shrink-0 font-medium text-gold-700 dark:text-gold-300">{{ formatDate(item.date) }}</span>
                            <span class="flex-1">{{ tr(item.label) }}</span>
                            <span class="shrink-0 text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.dash.inDays', { n: daysUntil(item.date) }) }}</span>
                        </li>
                    </ul>
                </Card>
            </div>
        </div>

        <!-- Finance: slips waiting and money coming in -->
        <div v-if="role === 'finance'" class="grid gap-6 xl:grid-cols-2">
            <Card>
                <h2 :class="h2"><Fa icon="receipt" class="text-gold-500" /> {{ t('admin.dash.slipsWaiting') }}</h2>
                <ul class="space-y-1">
                    <li v-for="payment in slipsWaiting" :key="payment.id">
                        <Link href="/admin/payments" :class="row">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ tr(payment.name) }}</span>
                                <span class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ payment.ref }} · {{ timeAgo(payment.slip.uploadedAt) }}</span>
                            </span>
                            <span class="text-sm font-semibold tabular-nums">{{ formatNumber(payment.slip.amount) }} {{ t('admin.payments.baht') }}</span>
                            <Fa v-if="payment.slip.amount !== payment.amount" icon="triangle-exclamation" class="text-red-500" :title="t('admin.payments.mismatchShort')" />
                            <Fa icon="chevron-right" class="text-xs text-neutral-400" />
                        </Link>
                    </li>
                </ul>
            </Card>
            <Card>
                <h2 :class="h2"><Fa icon="chart-line" class="text-gold-500" /> {{ t('admin.dash.receipts14') }}</h2>
                <TrendChart :points="recentReceipts" :unit="t('admin.payments.baht')" />
            </Card>
        </div>

        <!-- Roles without the Notifications menu (reviewers, authors, finance) read their notifications here -->
        <Card v-if="!hasNotificationsMenu">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 :class="[h2, 'mb-0']"><Fa icon="bell" class="text-gold-500" /> {{ t('admin.menu.notifications') }}</h2>
                <div class="flex items-center gap-4 text-sm">
                    <button v-if="unread" class="text-gold-600 hover:underline dark:text-gold-400" @click="markAllRead">{{ t('admin.notificationsPage.markAll') }}</button>
                    <Link v-if="role === 'reviewer'" href="/admin/reviews" class="text-gold-600 hover:underline dark:text-gold-400">{{ t('admin.dash.allReviews') }} <Fa icon="chevron-right" class="text-xs" /></Link>
                </div>
            </div>
            <ul class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                <li v-for="item in notifications" :key="item.id">
                    <Link
                        :href="item.href"
                        :class="item.read ? '' : 'bg-gold-50/60 dark:bg-gold-400/5'"
                        class="flex gap-3 p-3 transition hover:bg-gold-50 dark:hover:bg-gold-400/10"
                        @click="markRead(item)"
                    >
                        <span :class="item.read ? 'bg-neutral-100 text-neutral-500 dark:bg-white/5 dark:text-neutral-400' : 'bg-gold-100 text-gold-600 dark:bg-gold-400/15 dark:text-gold-400'" class="grid size-9 shrink-0 place-items-center rounded-full text-sm">
                            <Fa :icon="item.icon" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline justify-between gap-x-3">
                                <span :class="item.read ? '' : 'font-semibold'" class="text-sm">{{ tr(item.title) }}</span>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ timeAgo(item.at) }}</span>
                            </span>
                            <span class="block text-sm text-neutral-600 dark:text-neutral-300">{{ tr(item.body) }}</span>
                        </span>
                        <span v-if="!item.read" class="mt-2 size-2 shrink-0 rounded-full bg-gold-400" />
                    </Link>
                </li>
                <li v-if="!notifications.length" class="py-8 text-center text-sm text-neutral-500">{{ t('admin.notificationsPage.empty') }}</li>
            </ul>
        </Card>

        <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t(['editor', 'reviewer', 'author'].includes(role) ? 'admin.dash.realNote' : 'admin.dash.mockNote') }}</p>
    </div>
</template>
