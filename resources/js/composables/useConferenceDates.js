import { computed } from 'vue';
import { timelinePlan } from '@/data/mock';
import { useSite } from '@/composables/useSite';

/**
 * Dates authors may see on their paper's journey: only timeline rows marked public (the published schedule, so they
 * read as "scheduled", not as promises), plus the conference day. Timeline phase 1: the rows are still the assumed
 * plan in mock.js; once the timeline is saved this reads the saved plan instead.
 */
export function useConferenceDates() {
    const site = useSite();
    const published = (key) => timelinePlan.find((p) => p.key === key && p.public);

    return computed(() => ({
        notify: published('notify')?.start ?? null,
        cameraReady: published('cameraReady')?.end ?? null,
        conference: site.value.conference?.date ?? null,
    }));
}
