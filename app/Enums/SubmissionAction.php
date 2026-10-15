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
    case ReviewReminded = 'review_reminded';      // an editor reminded a reviewer; meta: reviewer, count
    case NoteAdded = 'note_added';                // an editor wrote an internal note (staff only)
    case ReviewUnavailable = 'review_unavailable'; // a reviewer told the editors they cannot review; meta: reviewer, reason
    case Decided = 'decided';                     // editor's decision after review (see to_status); meta: revision due
    case ReviewRoundStarted = 'review_round_started'; // editor sent a revised paper to reviewers again; meta: round
    case CameraReadyUploaded = 'camera_ready_uploaded'; // author uploaded (or replaced) the final file
    case JournalScreened = 'journal_screened';        // the journal's selection (see to_status: journal_selected or screening)
    case JournalResult = 'journal_result';            // the journal's final answer (see to_status: journal_accepted or screening)
    case PresentationScheduled = 'presentation_scheduled'; // meta: at, room (null = cleared)
    case SlidesUploaded = 'slides_uploaded';          // author uploaded (or replaced) the presentation slides

    /** Journal decisions: the author sees the outcome but never the journal's reason. */
    public function hidesCommentFromAuthor(): bool
    {
        return in_array($this, [self::JournalScreened, self::JournalResult], true);
    }

    /** Entries the author may see (without staff names). Reviewer activity stays hidden. */
    public function visibleToAuthor(): bool
    {
        return ! in_array($this, [self::TrackChanged, self::ReviewerAssigned, self::ReviewerRemoved, self::ReviewDueChanged, self::ReviewSubmitted, self::ReviewReminded, self::NoteAdded, self::ReviewUnavailable], true);
    }
}
