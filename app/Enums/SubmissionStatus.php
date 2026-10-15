<?php

namespace App\Enums;

/**
 * Where a paper is in the workflow (client decision 2026-10-15):
 * journal first; papers the journal does not take go through the conference track.
 */
enum SubmissionStatus: string
{
    // Journal (first gate)
    case Submitted = 'submitted';                  // waiting for the journal's selection (the author may edit until the deadline)
    case JournalSelected = 'journal_selected';     // the journal picked it; the author submits on the journal's site
    case JournalAccepted = 'journal_accepted';     // the journal accepted it: presents at the conference, no camera-ready

    // Conference track: screening
    case Screening = 'screening';                  // the journal did not take it; waiting for editor screening
    case Revision = 'screening_revision';          // editor sent it back at screening; the author may edit again
    case Rejected = 'screening_rejected';          // did not pass screening (final)

    // Conference track: peer review
    case InReview = 'in_review';                   // reviewers are evaluating it
    case RevisionMinor = 'revision_minor';         // editor decision after review: minor revision
    case RevisionMajor = 'revision_major';         // editor decision after review: major revision
    case Revised = 'revised';                      // author sent the revision; the editor decides or starts a new review round
    case Accepted = 'accepted';                    // accepted; waiting for the camera-ready file
    case ReviewRejected = 'rejected';              // rejected after review (final)
    case CameraReady = 'camera_ready';             // camera-ready file received

    /** Papers that can be given a presentation slot. */
    public function canBeScheduled(): bool
    {
        return in_array($this, [self::Accepted, self::CameraReady, self::JournalAccepted], true);
    }

    /** Papers whose presenters may upload slides (once they have a slot). */
    public function presents(): bool
    {
        return in_array($this, [self::CameraReady, self::JournalAccepted], true);
    }

    /** Statuses the journal works on and may see (papers in the conference track are hidden from it). */
    public static function journalStage(): array
    {
        return [self::Submitted, self::JournalSelected, self::JournalAccepted];
    }

    /** Screening decision (from the editor's form) => resulting status. */
    public static function fromScreening(string $decision): self
    {
        return match ($decision) {
            'pass' => self::InReview,
            'revise' => self::Revision,
            'reject' => self::Rejected,
        };
    }

    /** Editor decision after review => resulting status. */
    public static function fromDecision(string $decision): self
    {
        return match ($decision) {
            'accept' => self::Accepted,
            'minor' => self::RevisionMinor,
            'major' => self::RevisionMajor,
            'reject' => self::ReviewRejected,
        };
    }

    /** Statuses where the author is asked to revise and resubmit. */
    public function awaitsAuthorRevision(): bool
    {
        return in_array($this, [self::Revision, self::RevisionMinor, self::RevisionMajor], true);
    }
}
