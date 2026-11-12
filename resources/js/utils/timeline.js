/*
 * Date maths and order rules for the conference timeline (Settings › กำหนดการ; later the author's own timeline).
 * Dates are 'YYYY-MM-DD' strings counted as whole UTC days, so a time zone can never shift a phase by one day.
 */

const DAY = 86400000;

export const toDay = (date) => Math.round(Date.UTC(+date.slice(0, 4), +date.slice(5, 7) - 1, +date.slice(8, 10)) / DAY);
export const fromDay = (day) => new Date(day * DAY).toISOString().slice(0, 10);
export const addDays = (date, days) => fromDay(toDay(date) + days);
/** Days in a phase, both ends included. */
export const spanDays = (phase) => toDay(phase.end) - toDay(phase.start) + 1;
/** Today in the app's timezone (Asia/Bangkok), whatever the viewer's clock says. */
export const today = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });

/** Where a phase stands on a given day. */
export function phaseState(phase, day = today()) {
    if (phase.end < day) return 'done';
    if (phase.start > day) return 'upcoming';
    return 'active';
}

/*
 * The order the workflow needs: [phase, edge, rule, other phase, other edge].
 *   after: strictly later, notBefore: same day or later, notAfter: same day or earlier, before: strictly earlier
 */
export const RULES = [
    ['submission', 'start', 'notBefore', 'registration', 'start'],
    ['journalSelect', 'start', 'after', 'submission', 'end'], // papers are selected once submissions close
    ['journalAnswer', 'start', 'notBefore', 'journalSelect', 'start'],
    ['journalAnswer', 'end', 'notBefore', 'journalSelect', 'end'], // the last offer still gets its full answer time
    ['screening', 'start', 'after', 'submission', 'end'],
    ['journalAnswer', 'end', 'notAfter', 'screening', 'end'], // declined or lapsed offers still need screening
    ['review', 'start', 'notBefore', 'screening', 'start'],
    ['review', 'end', 'notBefore', 'screening', 'end'],
    ['notify', 'start', 'after', 'review', 'end'],
    ['revision', 'start', 'notBefore', 'notify', 'start'],
    ['finalDecision', 'end', 'notBefore', 'revision', 'end'],
    ['finalDecision', 'end', 'before', 'cameraReady', 'end'],
    ['cameraReady', 'start', 'notBefore', 'notify', 'start'],
    ['cameraReady', 'end', 'before', 'conference', 'start'],
    ['scheduling', 'end', 'before', 'conference', 'start'],
    ['slides', 'start', 'notBefore', 'scheduling', 'start'],
    ['slides', 'end', 'notAfter', 'conference', 'start'],
    ['registration', 'end', 'notAfter', 'conference', 'start'],
];

/**
 * Lay the enforced rows' real settings over a plan (a copy of useSchedulePlan()): submission period, paper selection,
 * the author's answer to a journal offer, slides cut-off and the conference day. `real` marks them, `source` says
 * whether their setting is set yet. settings = SettingsController::index `submission` (or the same keys from the
 * dashboard): { opens, deadline, journalDeadline, journalAnswerDays, slidesCutoffDays }.
 */
export function withRealDates(plan, settings, conferenceDate) {
    const row = (key) => plan.find((p) => p.key === key);
    const s = settings ?? {};

    const conference = row('conference');
    if (conferenceDate) Object.assign(conference, { start: conferenceDate, end: conferenceDate });
    Object.assign(conference, { real: true, source: 'set' });

    const submission = row('submission');
    if (s.opens) submission.start = s.opens.slice(0, 10);
    if (s.deadline) submission.end = s.deadline.slice(0, 10);
    Object.assign(submission, { real: true, source: s.opens || s.deadline ? 'set' : 'unset' });

    const journal = row('journalSelect');
    if (s.deadline) journal.start = addDays(submission.end, 1);
    if (s.journalDeadline) journal.end = s.journalDeadline;
    Object.assign(journal, { real: true, source: s.journalDeadline ? 'set' : 'unset' });

    // Authors answer an offer within journal_answer_days of it, so the last answers come that long after selection ends
    const answer = row('journalAnswer');
    const answerDays = Number(s.journalAnswerDays) || 7;
    Object.assign(answer, { start: journal.start, end: addDays(journal.end, answerDays), answerDays, real: true, source: 'set' });

    // Presenters can upload once their paper has a slot, so slides open when scheduling starts
    const slides = row('slides');
    slides.start = row('scheduling').start;
    const cutoff = s.slidesCutoffDays === null || s.slidesCutoffDays === undefined || s.slidesCutoffDays === '' ? null : Number(s.slidesCutoffDays);
    if (cutoff !== null) slides.end = addDays(conference.start, -cutoff);
    Object.assign(slides, { real: true, source: cutoff !== null ? 'set' : 'unset' });

    return plan;
}

const PASSES = {
    after: (a, b) => a > b,
    notBefore: (a, b) => a >= b,
    notAfter: (a, b) => a <= b,
    before: (a, b) => a < b,
};

/**
 * Problems in a plan: errors break the workflow order, warnings point at a phase too short for the time each paper
 * or reviewer gets. Each issue names the phase it belongs to, so the timeline can mark that row.
 */
export function checkPlan(phases) {
    const byKey = Object.fromEntries(phases.map((p) => [p.key, p]));
    const issues = [];

    phases.forEach((p) => {
        if (p.start > p.end) issues.push({ level: 'error', phase: p.key, type: 'range', params: { a: p.key } });
    });

    RULES.forEach(([a, aEdge, rule, b, bEdge]) => {
        const left = byKey[a];
        const right = byKey[b];
        if (!left || !right || PASSES[rule](left[aEdge], right[bEdge])) return;
        issues.push({ level: 'error', phase: a, type: rule, params: { a, aEdge: left.milestone ? null : aEdge, aDate: left[aEdge], b, bEdge: right.milestone ? null : bEdge, bDate: right[bEdge] } });
    });

    phases.forEach((p) => {
        if (p.perItem && p.start <= p.end && p.perItem.days > spanDays(p)) {
            issues.push({ level: 'warn', phase: p.key, type: 'short', params: { a: p.key, span: spanDays(p), days: p.perItem.days, unit: p.perItem.unit } });
        }
    });

    return issues;
}
