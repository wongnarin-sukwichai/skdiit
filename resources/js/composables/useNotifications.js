import { computed, reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { locale } from '@/i18n';
import { notificationsByRole } from '@/data/mock';

// Mock phase: one shared copy per role, so the header bell and the Notifications page stay in sync until reload
const lists = reactive({});

export function useNotifications() {
    const page = usePage();
    const role = computed(() => page.props.auth.user?.accessRole);

    if (role.value && !lists[role.value]) {
        lists[role.value] = (notificationsByRole[role.value] ?? []).map((n) => ({ ...n }));
    }

    const items = computed(() => [...(lists[role.value] ?? [])].sort((a, b) => b.at.localeCompare(a.at)));
    const unread = computed(() => items.value.filter((n) => !n.read).length);

    return {
        items,
        unread,
        markRead: (item) => !page.props.impersonation && (item.read = true),
        markAllRead: () => !page.props.impersonation && lists[role.value]?.forEach((n) => (n.read = true)),
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
