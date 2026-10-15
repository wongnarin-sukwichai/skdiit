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
    camera_ready_uploaded: 'file-circle-check',
    presentation_scheduled: 'person-chalkboard',
    slides_uploaded: 'file-powerpoint',
};

// Labelled by their outcome (to_status)
const BY_OUTCOME = ['screened', 'decided', 'journal_screened', 'journal_result'];

/** Screening, decision and journal entries are labelled by their outcome; the rest fill their meta into the text. */
export function eventTitle(t, event) {
    return BY_OUTCOME.includes(event.action) && event.toStatus
        ? t(`submissions.history.${event.action}.${event.toStatus}`)
        : t(`submissions.history.${event.action}`, event.meta ?? {});
}
