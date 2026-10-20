/*
 * The steps of a paper as its author sees them (AuthorJourney, AuthorJourneyMini). The journal, screening, review and
 * decisions are one "review" (under consideration) step; the journal is named only once it selects the paper.
 * Each step: { key, icon, state: done | current | upcoming | failed }
 *   editable: still a new paper inside the submission period (Submission::canBeEditedByAuthor while `submitted`)
 *   hadRevision: the author sent a revision before, so the revision step stays on the path as done
 */
export const REVISING = ['screening_revision', 'review_revision'];
export const REJECTED = ['screening_rejected', 'rejected'];
export const JOURNAL = ['journal_selected', 'journal_accepted'];

export function journeySteps({ status, editable = false, hadRevision = false }) {
    // After a revision the paper may go through screening or a new review round: that is the revision being considered
    const reconsidering = status === 'revised' || (hadRevision && ['screening', 'in_review'].includes(status));
    const steps = [];
    const add = (key, icon, state) => steps.push({ key, icon, state });

    add('submit', 'file-arrow-up', editable ? 'current' : 'done');
    add('review', 'magnifying-glass', editable ? 'upcoming' : (['submitted', 'screening', 'in_review'].includes(status) && !reconsidering ? 'current' : 'done'));

    // The journal path replaces the conference steps once the journal selects the paper
    if (JOURNAL.includes(status)) {
        add('journal', 'newspaper', status === 'journal_selected' ? 'current' : 'done');
        add('present', 'person-chalkboard', status === 'journal_accepted' ? 'current' : 'upcoming');
        return steps;
    }

    if (REVISING.includes(status) || status === 'revised' || hadRevision) {
        add('revision', 'pen', REVISING.includes(status) ? 'current' : 'done');
    }

    if (REJECTED.includes(status)) {
        add('result', 'flag', 'failed');
        return steps;
    }

    const decided = ['accepted', 'camera_ready'].includes(status);
    add('result', 'flag', decided ? 'done' : (reconsidering ? 'current' : 'upcoming'));
    add('final', 'file-circle-check', status === 'accepted' ? 'current' : (status === 'camera_ready' ? 'done' : 'upcoming'));
    add('present', 'person-chalkboard', status === 'camera_ready' ? 'current' : 'upcoming');
    return steps;
}
