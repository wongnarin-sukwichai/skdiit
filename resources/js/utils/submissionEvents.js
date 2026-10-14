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
    option_chosen: 'book',
    camera_ready_uploaded: 'file-circle-check',
    journal_result: 'newspaper',
    presentation_scheduled: 'person-chalkboard',
};

/** Screening and decision entries are labelled by their outcome; the rest fill their meta into the text. */
export function eventTitle(t, event) {
    return ['screened', 'decided'].includes(event.action)
        ? t(`submissions.history.${event.action}.${event.toStatus}`)
        : t(`submissions.history.${event.action}`, event.meta ?? {});
}
