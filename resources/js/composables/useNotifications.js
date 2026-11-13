import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { locale, useI18n } from '@/i18n';

/*
 * My notifications (real since 2026-11-13, App\Support\Notifier): the shared `notifications` prop holds the latest
 * 50 and the unread count. Header bell, home card and Notifications page all read this one composable. Text comes
 * from notifications.kind.{kind}.title / .body with the stored params, so it follows the language switch.
 */

// kind => [filter type, icon]
const KINDS = {
    journal_selected: ['paper', 'newspaper'],
    journal_passed_on: ['paper', 'newspaper'],
    journal_offer_reminder: ['paper', 'bell'],
    journal_offer_expired: ['paper', 'hourglass-half'],
    screening_pass: ['paper', 'circle-check'],
    screening_revise: ['paper', 'pen'],
    screening_reject: ['paper', 'ban'],
    decision_accept: ['paper', 'circle-check'],
    decision_accept_final: ['paper', 'circle-check'],
    decision_revise: ['paper', 'pen'],
    decision_reject: ['paper', 'ban'],
    presentation_scheduled: ['paper', 'person-chalkboard'],
    slides_reminder: ['paper', 'file-powerpoint'],
    revision_reminder: ['paper', 'bell'],
    revision_closed: ['paper', 'ban'],
    camera_ready_reminder: ['paper', 'bell'],
    camera_ready_approved: ['paper', 'circle-check'],
    camera_ready_fix: ['paper', 'pen'],
    acceptance_cancelled: ['paper', 'ban'],
    reviewer_assigned: ['review', 'clipboard-check'],
    review_reminder: ['review', 'bell'],
    reviewer_removed: ['review', 'user-xmark'],
    review_due_changed: ['review', 'calendar-days'],
    review_submitted: ['review', 'clipboard-check'],
    review_unavailable: ['review', 'user-xmark'],
    resubmitted: ['paper', 'rotate'],
    camera_ready_uploaded: ['paper', 'file-circle-check'],
    journal_answer_accept: ['paper', 'reply'],
    journal_answer_decline: ['paper', 'reply'],
    journal_answer_expired: ['paper', 'hourglass-half'],
    email_failed: ['system', 'envelope'],
};

// Read here before the server answers (and until the next page load brings the new state)
const localRead = reactive(new Set());
const allRead = ref(false);

function xsrf() {
    return decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}
function post(url) {
    return fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() } }).catch(() => {});
}

export function useNotifications() {
    const page = usePage();
    const { t, tr, formatDate } = useI18n();
    const data = computed(() => page.props.notifications ?? { items: [], unread: 0 });

    // A fresh page brings the server's own read state
    watch(data, () => { localRead.clear(); allRead.value = false; });

    const text = (n) => {
        const p = n.params ?? {};
        const params = {
            ...p,
            title: tr(p.title) ?? '',
            due: p.due ? formatDate(p.due, { day: 'numeric', month: 'short', year: 'numeric' }) : '',
            at: p.at ? formatDate(p.at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '',
        };
        return { title: t(`notifications.kind.${n.kind}.title`, params), body: t(`notifications.kind.${n.kind}.body`, params) };
    };

    const items = computed(() => {
        locale.value; // re-render the texts on a language switch
        return data.value.items.map((n) => ({
            ...n,
            ...text(n),
            type: KINDS[n.kind]?.[0] ?? 'system',
            icon: KINDS[n.kind]?.[1] ?? 'bell',
            read: n.read || allRead.value || localRead.has(n.id),
        }));
    });
    const unread = computed(() => (allRead.value ? 0 : Math.max(0, data.value.unread - data.value.items.filter((n) => !n.read && localRead.has(n.id)).length)));
    const readOnly = () => !!page.props.impersonation;

    return {
        items,
        unread,
        markRead: (item) => {
            if (readOnly() || item.read) return;
            localRead.add(item.id);
            post(`/admin/notifications/${item.id}/read`);
        },
        markAllRead: () => {
            if (readOnly() || !unread.value) return;
            allRead.value = true;
            post('/admin/notifications/read-all');
        },
    };
}

/** "5 minutes ago" / "5 นาทีที่แล้ว" in the current language. */
export function timeAgo(iso) {
    const seconds = Math.round((new Date(iso) - Date.now()) / 1000);
    const format = new Intl.RelativeTimeFormat(locale.value === 'th' ? 'th-TH' : 'en', { numeric: 'auto' });
    const units = [['day', 86400], ['hour', 3600], ['minute', 60]];
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit);
    }
    return format.format(0, 'minute');
}
