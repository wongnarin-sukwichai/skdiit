import { computed } from 'vue';
import { useSchedulePlan } from '@/composables/useSchedulePlan';
import { useSite } from '@/composables/useSite';

/**
 * Dates authors may see on their paper's journey: only timeline rows marked public (the published schedule, so they
 * read as "scheduled", not as promises), plus the conference day. Saved rows win over the default plan (useSchedulePlan).
 */
export function useConferenceDates() {
    const site = useSite();
    const plan = useSchedulePlan();
    const published = (key) => plan.value.find((p) => p.key === key && p.public);

    return computed(() => ({
        notify: published('notify')?.start ?? null,
        cameraReady: published('cameraReady')?.end ?? null,
        conference: site.value.conference?.date ?? null,
    }));
}
