<?php

namespace App\Enums;

/** Kinds of entries in a paper's history (submission_events). */
enum SubmissionAction: string
{
    case Submitted = 'submitted';       // author sent the paper
    case Updated = 'updated';           // author edited it before the deadline
    case Resubmitted = 'resubmitted';   // author sent a revised version
    case Screened = 'screened';         // editor's screening decision (see to_status)
    case TrackChanged = 'track_changed';
    case ReviewerAssigned = 'reviewer_assigned';  // meta: reviewer, due
    case ReviewerRemoved = 'reviewer_removed';    // meta: reviewer
    case ReviewDueChanged = 'review_due_changed'; // meta: reviewer, from, to
    case ReviewSubmitted = 'review_submitted';    // meta: recommendation (reviewer is the actor)
    case Decided = 'decided';                     // editor's decision after review (see to_status); meta: revision due
    case ReviewRoundStarted = 'review_round_started'; // editor sent a revised paper to reviewers again; meta: round
    case OptionChosen = 'option_chosen';              // author chose A or B; meta: option
    case CameraReadyUploaded = 'camera_ready_uploaded'; // author uploaded (or replaced) the final file
    case JournalResult = 'journal_result';            // editor recorded the journal's answer; meta: journal, result
    case PresentationScheduled = 'presentation_scheduled'; // meta: at, room (null = cleared)

    /** Entries the author may see (without staff names). Reviewer activity stays hidden. */
    public function visibleToAuthor(): bool
    {
        return ! in_array($this, [self::TrackChanged, self::ReviewerAssigned, self::ReviewerRemoved, self::ReviewDueChanged, self::ReviewSubmitted], true);
    }
}
