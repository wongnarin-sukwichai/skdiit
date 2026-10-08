<?php

namespace App\Enums;

/**
 * Where a paper is in the workflow (public/data/workflow_backend.jpg).
 * Later steps (publishing option A/B, ...) are added as they are built.
 */
enum SubmissionStatus: string
{
    // Screening
    case Submitted = 'submitted';                  // waiting for editor screening
    case Revision = 'screening_revision';          // editor sent it back at screening; the author may edit again
    case Rejected = 'screening_rejected';          // did not pass screening (final)

    // Peer review
    case InReview = 'in_review';                   // reviewers are evaluating it
    case RevisionMinor = 'revision_minor';         // editor decision after review: minor revision
    case RevisionMajor = 'revision_major';         // editor decision after review: major revision
    case Revised = 'revised';                      // author sent the revision; the editor decides or starts a new review round
    case Accepted = 'accepted';                    // accepted; the author chooses publishing option A or B
    case ReviewRejected = 'rejected';              // rejected after review (final)

    // Publication
    case OptionA = 'option_a';                     // Proceeding: waiting for the camera-ready file
    case CameraReady = 'camera_ready';             // Proceeding: camera-ready file received
    case OptionB = 'option_b';                     // journal: forwarded by the editor, waiting for the journal
    case JournalAccepted = 'journal_accepted';     // journal accepted it (a journal rejection moves it to OptionA)

    /** Papers that can be given a presentation slot. */
    public function canBeScheduled(): bool
    {
        return in_array($this, [self::OptionA, self::CameraReady, self::OptionB, self::JournalAccepted], true);
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
