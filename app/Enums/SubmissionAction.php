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
    case CameraReadyApproved = 'camera_ready_approved'; // an editor checked the camera-ready file: into the proceedings (2026-11-12)
    case CameraReadyFixRequested = 'camera_ready_fix_requested'; // an editor sent the camera-ready file back for fixes; meta: file
    case AcceptanceCancelled = 'acceptance_cancelled'; // an editor withdrew the acceptance (e.g. plagiarism found, withdrawn); comment: reason
    case JournalScreened = 'journal_screened';        // first gate: offered to a journal (to_status journal_selected; meta: journal_id, journal, due) or sent to the conference (screening)
    case JournalResult = 'journal_result';            // before 2026-11-11: the journal's final answer (kept for old history)
    case JournalOfferAnswered = 'journal_offer_answered'; // the author answered the offer (to_status journal_accepted / screening); meta: answer accept|decline|expired, journal
    case JournalOfferReminded = 'journal_offer_reminded'; // the author was reminded to answer the offer; meta: journal, due
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
