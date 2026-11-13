// Paper history entries (App\Enums\SubmissionAction): icon and label, shared by the paper page and the home page

export const eventIcons = {
    submitted: 'paper-plane',
    updated: 'pen',
    resubmitted: 'arrow-rotate-right',
    screened: 'clipboard-check',
    track_changed: 'layer-group',
    reviewer_assigned: 'user-plus',
    reviewer_removed: 'user-xmark',
    review_due_changed: 'calendar-days',
    review_submitted: 'clipboard-check',
    review_reminded: 'bell',
    note_added: 'note-sticky',
    review_unavailable: 'user-xmark',
    decided: 'gavel',
    review_round_started: 'user-check',
    journal_screened: 'newspaper',
    journal_result: 'newspaper',
    journal_offer_answered: 'reply',
    journal_offer_reminded: 'bell',
    camera_ready_uploaded: 'file-circle-check',
    camera_ready_approved: 'circle-check',
    camera_ready_fix_requested: 'pen',
    acceptance_cancelled: 'ban',
    presentation_scheduled: 'person-chalkboard',
    slides_uploaded: 'file-powerpoint',
    slides_reminded: 'bell',
    author_reminded: 'bell',
    revision_closed: 'ban',
};

// Labelled by their outcome (to_status)
const BY_OUTCOME = ['screened', 'decided', 'journal_screened', 'journal_result'];

/**
 * Screening, decision and first-gate entries are labelled by their outcome (the journal offered fills in its name),
 * the author's answer to a journal offer by the answer; the rest fill their meta into the text.
 */
export function eventTitle(t, event) {
    if (event.action === 'author_reminded') {
        return t(`submissions.history.author_reminded.${event.meta?.task === 'cameraReady' ? 'cameraReady' : 'revision'}`);
    }
    if (event.action === 'camera_ready_approved' && event.meta?.from_latest) {
        return t('submissions.history.camera_ready_from_latest', { version: event.meta.version ?? '' });
    }
    if (event.action === 'journal_offer_answered') {
        return t(`submissions.history.journal_offer_answered.${event.meta?.answer ?? 'decline'}`, { journal: event.meta?.journal ?? '' });
    }
    return BY_OUTCOME.includes(event.action) && event.toStatus
        ? t(`submissions.history.${event.action}.${event.toStatus}`, { journal: '', ...(event.meta ?? {}) })
        : t(`submissions.history.${event.action}`, event.meta ?? {});
}
