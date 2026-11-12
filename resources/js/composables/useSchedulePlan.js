import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { timelinePlan } from '@/data/mock';

/**
 * The conference timeline: the default plan (timelinePlan: icon, who, per-item unit, default dates) with the rows
 * saved in Settings › กำหนดการ laid over it (shared `schedulePlan`, App\Support\SchedulePlan). `saved` marks rows
 * stored in the database. The five rows the system enforces are overlaid by ScheduleTab from their own settings.
 */
export function useSchedulePlan() {
    const page = usePage();

    return computed(() => {
        const saved = page.props.schedulePlan ?? {};
        return timelinePlan.map((p) => {
            const row = saved[p.key];
            if (!row) return { ...p, perItem: p.perItem && { ...p.perItem } };
            return {
                ...p,
                start: row.start,
                end: p.milestone ? row.start : row.end,
                mode: row.mode ?? p.mode,
                public: !!row.public,
                perItem: p.perItem && { ...p.perItem, days: row.perItemDays ?? p.perItem.days },
                saved: true,
            };
        });
    });
}
