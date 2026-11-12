/*
 * The steps of a paper as its author sees them (AuthorJourney, AuthorJourneyMini, AuthorPapersBoard).
 * Since 2026-11-12 the path only moves forward: a revision is not a step of its own (which made the current step jump
 * back and forth) but a badge on the step the paper is at, like the editors' tabs:
 *   submit → screening (first gate and editor screening) → peerReview → result → final → present
 *   journal path: submit → screening → journal → present (the journal is named only once it selects the paper)
 * Each step: { key, icon, state: done | current | upcoming | failed, badge?: revising | revisedWaiting }
 *   editable: still a new paper inside the submission period (Submission::canBeEditedByAuthor while `submitted`)
 *   hadRevision: the author sent a revision before (a paper back at screening is then a revision waiting to be checked)
 */
export const REVISING = ['screening_revision', 'review_revision'];
export const REJECTED = ['screening_rejected', 'rejected'];
export const JOURNAL = ['journal_selected', 'journal_accepted'];

const SCREENING = ['submitted', 'screening', 'screening_revision'];
const PEER_REVIEW = ['in_review', 'review_revision', 'revised'];
const DECIDED = ['accepted', 'camera_ready'];

export function journeySteps({ status, editable = false, hadRevision = false, finalApproved = false, finalFix = false }) {
    const steps = [];
    const add = (key, icon, state, badge = null) => steps.push({ key, icon, state, ...(badge ? { badge } : {}) });

    add('submit', 'file-arrow-up', editable ? 'current' : 'done');

    // First gate and editor screening; sent back at screening = the author revises here
    const screening = editable ? 'upcoming' : status === 'screening_rejected' ? 'failed' : SCREENING.includes(status) ? 'current' : 'done';
    const screeningBadge = status === 'screening_revision' ? 'revising' : status === 'screening' && hadRevision ? 'revisedWaiting' : null;
    add('screening', 'magnifying-glass', screening, screeningBadge);

    if (JOURNAL.includes(status)) {
        add('journal', 'newspaper', status === 'journal_selected' ? 'current' : 'done');
        add('present', 'person-chalkboard', status === 'journal_accepted' ? 'current' : 'upcoming');
        return steps;
    }
    if (status === 'screening_rejected') return steps;

    // Peer review; sent back after review = the author revises here, then the editors check the revision here
    const peerReview = PEER_REVIEW.includes(status) ? 'current' : DECIDED.includes(status) || status === 'rejected' ? 'done' : 'upcoming';
    const reviewBadge = status === 'review_revision' ? 'revising' : status === 'revised' ? 'revisedWaiting' : null;
    add('peerReview', 'user-check', peerReview, reviewBadge);

    if (status === 'rejected') {
        add('result', 'flag', 'failed');
        return steps;
    }
    add('result', 'flag', DECIDED.includes(status) ? 'done' : 'upcoming');
    // Camera-ready: sent and checked by the editors before it is done (fixes come back as "your revision")
    const approved = status === 'camera_ready' && finalApproved;
    const finalBadge = status === 'accepted' && finalFix ? 'revising' : status === 'camera_ready' && !finalApproved ? 'checking' : null;
    add('final', 'file-circle-check', approved ? 'done' : ['accepted', 'camera_ready'].includes(status) ? 'current' : 'upcoming', finalBadge);
    add('present', 'person-chalkboard', approved ? 'current' : 'upcoming');
    return steps;
}
