<?php

namespace App\Enums;

/**
 * Where a paper is in the workflow. First gate (client request 2026-11-11): after the submission deadline an editor
 * (or the journal role) sets the track and either offers the paper to one partner journal or sends it to the
 * conference track. The author answers the offer: accepting ends the paper's conference process until the slides,
 * declining (or not answering in time) sends it to the conference track.
 */
enum SubmissionStatus: string
{
    // First gate
    case Submitted = 'submitted';                  // waiting for the first gate (the author may edit until the deadline)
    case JournalSelected = 'journal_selected';     // offered to a journal (submissions.journal_id); waiting for the author's answer
    case JournalAccepted = 'journal_accepted';     // the author accepted: submits on the journal's site, presents at the conference, no camera-ready

    // Conference track: screening
    case Screening = 'screening';                  // the journal did not take it; waiting for editor screening
    case Revision = 'screening_revision';          // editor sent it back at screening; the author may edit again
    case Rejected = 'screening_rejected';          // did not pass screening (final)

    // Conference track: peer review
    case InReview = 'in_review';                   // reviewers are evaluating it
    case ReviewRevision = 'review_revision';       // editor decision after review: revise and resubmit
    case Revised = 'revised';                      // author sent the revision; the editor decides or starts a new review round
    case Accepted = 'accepted';                    // accepted; waiting for the camera-ready file
    case ReviewRejected = 'rejected';              // rejected after review (final)
    case CameraReady = 'camera_ready';             // camera-ready file received

    /** Label for server-made files (Excel export); the pages use submissions.status.* in the i18n files. */
    public function label(string $lang = 'th'): string
    {
        [$th, $en] = match ($this) {
            self::Submitted => ['รอคัดเลือก', 'Awaiting selection'],
            self::JournalSelected => ['เสนอวารสาร รอผู้ส่งตอบรับ', 'Offered to a journal: awaiting the author'],
            self::JournalAccepted => ['ผู้ส่งตอบรับวารสาร', 'Author accepted the journal'],
            self::Screening => ['รอคัดกรอง', 'Awaiting screening'],
            self::Revision => ['ส่งกลับให้แก้ไข', 'Revision requested'],
            self::Rejected => ['ไม่ผ่านการคัดกรอง', 'Rejected at screening'],
            self::InReview => ['รอผลประเมิน', 'Awaiting peer review'],
            self::ReviewRevision => ['ขอให้แก้ไข', 'Revision requested'],
            self::Revised => ['ส่งฉบับแก้ไขแล้ว', 'Revision received'],
            self::Accepted => ['ผ่านการประเมิน รอฉบับสมบูรณ์', 'Accepted: awaiting camera-ready'],
            self::ReviewRejected => ['ไม่ผ่านการประเมิน', 'Rejected after review'],
            self::CameraReady => ['ได้รับฉบับสมบูรณ์แล้ว', 'Camera-ready received'],
        };

        return $lang === 'en' ? $en : $th;
    }

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

    /** Statuses of the first gate (before the conference track). */
    public static function journalStage(): array
    {
        return [self::Submitted, self::JournalSelected, self::JournalAccepted];
    }

    /** The conference track: every status after the first gate. */
    public static function conferenceTrack(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! in_array($status, self::journalStage(), true)));
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
            'revise' => self::ReviewRevision,
            'reject' => self::ReviewRejected,
        };
    }

    /** Statuses where the author is asked to revise and resubmit. */
    public function awaitsAuthorRevision(): bool
    {
        return in_array($this, [self::Revision, self::ReviewRevision], true);
    }
}
