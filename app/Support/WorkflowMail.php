<?php

namespace App\Support;

use App\Enums\SubmissionAction;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\SubmissionEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The email that goes with a paper's workflow step (SubmissionEvent::created): which template, to whom.
 * Authors get their own paper's news; reviewers get assignments and reminders. The journal's reasons and
 * internal notes are never emailed. A failed send is reported and never blocks the step itself.
 */
class WorkflowMail
{
    public static function forEvent(SubmissionEvent $event): void
    {
        $mail = static::compose($event);
        if ($mail) {
            static::send($mail['to'], $mail['key'], $mail['subject'], $mail['body_th'], $mail['body_en'], $mail['url'], $event->submission->id);
        }
    }

    /**
     * The email a step would send, without sending it: { to, key, subject, body_th, body_en, url } or null.
     * Also used to show editors the exact email before they confirm (2026-11-13, e.g. the reminder preview).
     */
    public static function compose(SubmissionEvent $event): ?array
    {
        $submission = $event->submission;
        $author = $submission?->user;
        if (! $author) {
            return null;
        }

        [$key, $to] = match ($event->action) {
            SubmissionAction::Submitted => ['paper_received', $author],
            // First gate: offered to a journal (the author answers), or sent to the conference track
            SubmissionAction::JournalScreened => [$event->to_status === 'journal_selected' ? 'journal_selected' : 'journal_passed_on', $author],
            SubmissionAction::JournalOfferAnswered => [match ($event->meta['answer'] ?? null) {
                'accept' => 'journal_accepted',
                'expired' => 'journal_offer_expired',
                default => 'journal_passed_on',
            }, $author],
            SubmissionAction::JournalOfferReminded => ['journal_offer_reminder', $author],
            SubmissionAction::Screened => [match ($event->to_status) {
                'in_review' => 'screening_pass',
                'screening_revision' => 'screening_revise',
                default => 'screening_reject',
            }, $author],
            SubmissionAction::Decided => [match ($event->to_status) {
                'accepted' => ($event->meta['final_from_latest'] ?? false) ? 'decision_accept_final' : 'decision_accept',
                'review_revision' => 'decision_revise',
                default => 'decision_reject',
            }, $author],
            SubmissionAction::PresentationScheduled => [$event->meta['at'] ?? null ? 'presentation_scheduled' : null, $author],
            SubmissionAction::ReviewerAssigned => ['reviewer_assigned', User::find($event->meta['reviewer_id'] ?? null)],
            SubmissionAction::ReviewReminded => ['review_reminder', User::find($event->meta['reviewer_id'] ?? null)],
            // 2026-11-13: the reviewer also hears when they are taken off a paper or the due date moves
            SubmissionAction::ReviewerRemoved => ['reviewer_removed', User::find($event->meta['reviewer_id'] ?? null)],
            SubmissionAction::ReviewDueChanged => ['review_due_changed', User::find($event->meta['reviewer_id'] ?? null)],
            SubmissionAction::SlidesReminded => ['slides_reminder', $author],
            SubmissionAction::RevisionClosed => ['revision_closed', $author],
            SubmissionAction::AuthorReminded => [($event->meta['task'] ?? null) === 'cameraReady' ? 'camera_ready_reminder' : 'revision_reminder', $author],
            // Approved together with "accept": the decision email already says so
            SubmissionAction::CameraReadyApproved => [($event->meta['with_decision'] ?? false) ? null : 'camera_ready_approved', $author],
            SubmissionAction::CameraReadyFixRequested => ['camera_ready_fix', $author],
            SubmissionAction::AcceptanceCancelled => ['acceptance_cancelled', $author],
            default => [null, null],
        };
        if (! $key || ! $to) {
            return null;
        }

        $toReviewer = in_array($key, ['reviewer_assigned', 'review_reminder', 'reviewer_removed', 'review_due_changed'], true);
        $day = fn (?string $date) => $date ? Carbon::parse($date)->locale('th')->translatedFormat('j F').' '.(Carbon::parse($date)->year + 543) : '';
        // The journal's reasons stay with the staff; editors' comments go to the author
        $comment = $event->action->hidesCommentFromAuthor() ? '' : (string) $event->comment;

        // English text: the English title and dates (e.g. "10 November 2026"), the rest as in Thai
        $dayEn = fn (?string $date) => $date ? Carbon::parse($date)->locale('en')->translatedFormat('j F Y') : '';
        $dueDate = $event->meta['due'] ?? $event->meta['to'] ?? $submission->revision_due_date?->toDateString();
        $dueTime = isset($event->meta['due_time']) ? ' '.$event->meta['due_time'] : '';
        $english = [
            'title' => $submission->title_en ?: $submission->title_th,
            'due' => $dueDate ? $dayEn($dueDate).$dueTime : '',
            'date' => isset($event->meta['at']) ? $dayEn($event->meta['at']).' '.Carbon::parse($event->meta['at'])->format('H:i') : '',
        ];

        $text = EmailTemplate::render($key, [
            'name' => $to->name,
            'code' => $submission->code,
            'title' => $submission->title_th ?: $submission->title_en, // Thai is optional
            'link' => url($toReviewer ? '/admin/reviews' : "/admin/my-submissions/{$submission->id}"),
            'comment' => $comment,
            'due' => $day($event->meta['due'] ?? $event->meta['to'] ?? $submission->revision_due_date?->toDateString()).(isset($event->meta['due_time']) ? ' '.$event->meta['due_time'] : ''),
            'journal' => (string) ($event->meta['journal'] ?? $submission->journal?->name),
            'journal_url' => (string) $submission->journal?->url,
            'date' => isset($event->meta['at']) ? $day($event->meta['at']).' '.Carbon::parse($event->meta['at'])->format('H:i') : '',
            'room' => $event->meta['room'] ?? '',
        ], $english);

        return [...$text, 'to' => $to, 'key' => $key, 'url' => url($toReviewer ? '/admin/reviews' : "/admin/my-submissions/{$submission->id}")];
    }

    /** An account email (EmailTemplate::ACCOUNT_KEYS; none in use since 2026-11-13). */
    public static function account(string $key, User $to, string $path, array $vars = []): bool
    {
        $text = EmailTemplate::render($key, ['name' => $to->name, 'link' => url($path), ...$vars]);

        return static::send($to, $key, $text['subject'], $text['body_th'], $text['body_en'], url($path));
    }

    /** Send and record it in the email log (EmailLog); kind = template key or 'announcement'. Never throws. */
    public static function send(User $to, string $kind, string $subject, string $bodyTh, ?string $bodyEn, ?string $url, ?int $submissionId = null): bool
    {
        return EmailLog::deliver($to, $kind, $subject, $bodyTh, $bodyEn, $url, $submissionId);
    }
}
