<?php

namespace App\Support;

use App\Enums\SubmissionAction;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\SubmissionEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The email that goes with a paper's workflow step (SubmissionEvent::created): which template, to whom.
 * Authors get their own paper's news; reviewers get assignments and reminders. The journal's reasons and
 * internal notes are never emailed. A failed send is reported and never blocks the step itself.
 */
class WorkflowMail
{
    public static function forEvent(SubmissionEvent $event): void
    {
        $submission = $event->submission;
        $author = $submission?->user;
        if (! $author) {
            return;
        }

        [$key, $to] = match ($event->action) {
            SubmissionAction::Submitted => ['paper_received', $author],
            SubmissionAction::JournalScreened, SubmissionAction::JournalResult => [match ($event->to_status) {
                'journal_selected' => 'journal_selected',
                'journal_accepted' => 'journal_accepted',
                default => 'journal_passed_on',
            }, $author],
            SubmissionAction::Screened => [match ($event->to_status) {
                'in_review' => 'screening_pass',
                'screening_revision' => 'screening_revise',
                default => 'screening_reject',
            }, $author],
            SubmissionAction::Decided => [match ($event->to_status) {
                'accepted' => 'decision_accept',
                'revision_minor' => 'decision_minor',
                'revision_major' => 'decision_major',
                default => 'decision_reject',
            }, $author],
            SubmissionAction::PresentationScheduled => [$event->meta['at'] ?? null ? 'presentation_scheduled' : null, $author],
            SubmissionAction::ReviewerAssigned => ['reviewer_assigned', User::find($event->meta['reviewer_id'] ?? null)],
            SubmissionAction::ReviewReminded => ['review_reminder', User::find($event->meta['reviewer_id'] ?? null)],
            default => [null, null],
        };
        if (! $key || ! $to) {
            return;
        }

        $toReviewer = in_array($key, ['reviewer_assigned', 'review_reminder'], true);
        $day = fn (?string $date) => $date ? Carbon::parse($date)->locale('th')->translatedFormat('j F').' '.(Carbon::parse($date)->year + 543) : '';
        // The journal's reasons stay with the staff; editors' comments go to the author
        $comment = $event->action->hidesCommentFromAuthor() ? '' : (string) $event->comment;

        $text = EmailTemplate::render($key, [
            'name' => $to->name,
            'code' => $submission->code,
            'title' => $submission->title_th,
            'link' => url($toReviewer ? '/admin/reviews' : "/admin/my-submissions/{$submission->id}"),
            'comment' => $comment,
            'due' => $day($event->meta['due'] ?? $submission->revision_due_date?->toDateString()),
            'journal_url' => (string) Setting::get('journal_url'),
            'date' => isset($event->meta['at']) ? $day($event->meta['at']).' '.Carbon::parse($event->meta['at'])->format('H:i') : '',
            'room' => $event->meta['room'] ?? '',
        ]);

        static::send($to, $text['subject'], $text['body_th'], $text['body_en'], url($toReviewer ? '/admin/reviews' : "/admin/my-submissions/{$submission->id}"));
    }

    public static function send(User $to, string $subject, string $bodyTh, ?string $bodyEn, ?string $url): bool
    {
        try {
            Mail::to($to)->send(new TemplatedMail($subject, $bodyTh, $bodyEn, $url));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
