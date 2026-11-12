import { computed } from 'vue';
import { useSite } from '@/composables/useSite';
import { useSchedulePlan } from '@/composables/useSchedulePlan';
import { phaseState, toDay, today as todayIn, withRealDates } from '@/utils/timeline';

/**
 * The editor's seven stages along the conference schedule (2026-11-12), for the big card on the editor/admin home
 * (EditorTimelineCard) and the slim bar on every other backend page (EditorTimeline). `data` is the shared
 * `editorStages` prop (App\Support\EditorStages): work queues, stage counts and the enforced dates, laid over the
 * saved schedule. Each stage: { key, icon, start, end, count, href, state (done|active|upcoming), late }.
 */
export function useEditorStages(data) {
    const site = useSite();
    const schedule = useSchedulePlan();
    const today = todayIn();

    const plan = computed(() => withRealDates(schedule.value.map((p) => ({ ...p })), data.value.schedule, site.value.conference?.date));
    const dates = (key) => {
        const p = plan.value.find((x) => x.key === key);
        return { start: p.start, end: p.end };
    };
    const tab = (name) => `/admin/submissions?stage=${name}`;

    const stages = computed(() => {
        const st = data.value.stages;
        const tb = data.value.tabs;
        const review = dates('review');
        return [
            { key: 'selection', icon: 'code-branch', ...dates('journalSelect'), count: st.selection, href: '/admin/selection?tab=waiting' },
            // The stages follow the schedule (Settings › กำหนดการ) for the overview; each opens the matching Submissions tab.
            // Screening counts new papers and revisions sent back after screening alike
            { key: 'screening', icon: 'magnifying-glass', ...dates('screening'), count: tb.screening.count + tb.revisedScreening.count, href: tab(tb.screening.count || !tb.revisedScreening.count ? 'screening' : 'revisedScreening') },
            { key: 'review', icon: 'user-check', ...review, count: tb.findReviewers.count, href: tab('findReviewers') },
            // Decisions are due by the notification day; they can start once reviews come in
            { key: 'decide', icon: 'gavel', start: review.start, end: dates('notify').start, count: data.value.queues.readyToDecide, href: tab('decide') },
            { key: 'finalDecision', icon: 'rotate', ...dates('finalDecision'), count: data.value.queues.revised, revising: st.revising ?? 0, href: tab('decide') },
            // Camera-ready files waiting for the editors' check
            { key: 'cameraReady', icon: 'file-circle-check', ...dates('cameraReady'), count: st.cameraReadyToCheck ?? 0, href: tab('awaitingFinal') },
            { key: 'scheduling', icon: 'table-columns', ...dates('scheduling'), count: st.scheduling, href: '/admin/presentations' },
        ].map((stage) => {
            const state = phaseState(stage, today);
            return { ...stage, state, late: state === 'done' && stage.count > 0 && !stage.authorsTurn };
        });
    });

    return {
        stages,
        active: computed(() => stages.value.filter((s) => s.state === 'active')),
        next: computed(() => stages.value.find((s) => s.state === 'upcoming')),
        daysLeft: (s) => toDay(s.end) - toDay(today),
        daysTo: (s) => toDay(s.start) - toDay(today),
    };
}
