<?php

namespace App\Support;

use App\Enums\SubmissionAction;
use App\Models\EmailLog;
use App\Models\SubmissionEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Who hears about what inside the system (2026-11-13, agreed with the user):
 * - the author: every step that also emails them (selection, screening, decision, camera-ready, slot, reminders);
 * - the reviewer: invited, reminded, removed, due date changed;
 * - admin and editors: a review sent, a reviewer who cannot review, a revision, a camera-ready file, a journal
 *   answer (in the system only, no email to editors yet);
 * - admin: an email that could not be sent.
 * Nobody is notified of their own action. A failure here never blocks the step itself.
 */
class Notifier
{
    public static function forEvent(SubmissionEvent $event): void
    {
        try {
            static::handle($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function handle(SubmissionEvent $event): void
    {
        $paper = $event->submission;
        if (! $paper) {
            return;
        }
        $meta = $event->meta ?? [];
        $base = ['code' => $paper->code, 'title' => ['en' => $paper->title_en, 'th' => $paper->title_th ?: $paper->title_en]];
        $toAuthor = fn (string $kind, array $params = []) => static::send(collect([$paper->user]), $event, $kind, [...$base, ...$params], "/admin/my-submissions/{$paper->id}");
        $toReviewer = fn (string $kind, array $params = []) => static::send(collect([User::find($meta['reviewer_id'] ?? null)]), $event, $kind, [...$base, ...$params], '/admin/reviews');
        $toStaff = fn (string $kind, array $params = [], ?string $href = null) => static::send(static::staff(), $event, $kind, [...$base, ...$params], $href ?? "/admin/submissions/{$paper->id}");

        match ($event->action) {
            SubmissionAction::JournalScreened => $event->to_status === 'journal_selected'
                ? $toAuthor('journal_selected', ['journal' => $meta['journal'] ?? '', 'due' => $meta['due'] ?? null])
                : $toAuthor('journal_passed_on'),
            SubmissionAction::JournalOfferAnswered => static::journalAnswer($event, $toAuthor, $toStaff, $paper->id),
            SubmissionAction::JournalOfferReminded => $toAuthor('journal_offer_reminder', ['journal' => $meta['journal'] ?? '', 'due' => $meta['due'] ?? null]),
            SubmissionAction::Screened => $toAuthor(match ($event->to_status) {
                'in_review' => 'screening_pass',
                'screening_revision' => 'screening_revise',
                default => 'screening_reject',
            }),
            SubmissionAction::Decided => $toAuthor(match ($event->to_status) {
                'accepted' => ($meta['final_from_latest'] ?? false) ? 'decision_accept_final' : 'decision_accept',
                'review_revision' => 'decision_revise',
                default => 'decision_reject',
            }, ['due' => $meta['due'] ?? $paper->revision_due_date?->toDateString()]),
            SubmissionAction::PresentationScheduled => ($meta['at'] ?? null) ? $toAuthor('presentation_scheduled', ['at' => $meta['at'], 'room' => $meta['room'] ?? '']) : null,
            SubmissionAction::SlidesReminded => $toAuthor('slides_reminder', ['due' => $meta['due'] ?? null]),
            SubmissionAction::RevisionClosed => $toAuthor('revision_closed'),
            SubmissionAction::AuthorReminded => $toAuthor(($meta['task'] ?? null) === 'cameraReady' ? 'camera_ready_reminder' : 'revision_reminder', ['due' => $meta['due'] ?? null]),
            SubmissionAction::CameraReadyApproved => ($meta['with_decision'] ?? false) ? null : $toAuthor('camera_ready_approved'),
            SubmissionAction::CameraReadyFixRequested => $toAuthor('camera_ready_fix'),
            SubmissionAction::AcceptanceCancelled => $toAuthor('acceptance_cancelled'),
            SubmissionAction::ReviewerAssigned => $toReviewer('reviewer_assigned', ['due' => $meta['due'] ?? null]),
            SubmissionAction::ReviewReminded => $toReviewer('review_reminder', ['due' => $meta['due'] ?? null]),
            SubmissionAction::ReviewerRemoved => $toReviewer('reviewer_removed'),
            SubmissionAction::ReviewDueChanged => $toReviewer('review_due_changed', ['due' => $meta['to'] ?? null]),
            SubmissionAction::ReviewSubmitted => $toStaff('review_submitted', ['reviewer' => $event->user?->name ?? '']),
            SubmissionAction::ReviewUnavailable => $toStaff('review_unavailable', ['reviewer' => $meta['reviewer'] ?? '']),
            SubmissionAction::Resubmitted => $toStaff('resubmitted'),
            SubmissionAction::CameraReadyUploaded => $toStaff('camera_ready_uploaded'),
            default => null,
        };
    }

    /** The author's answer goes to the staff; an offer that lapsed goes to the author too. */
    private static function journalAnswer(SubmissionEvent $event, callable $toAuthor, callable $toStaff, int $paperId): void
    {
        $answer = $event->meta['answer'] ?? 'decline';
        $journal = $event->meta['journal'] ?? '';
        if ($answer === 'expired') {
            $toAuthor('journal_offer_expired', ['journal' => $journal]);
        }
        $toStaff("journal_answer_{$answer}", ['journal' => $journal], "/admin/selection/{$paperId}");
    }

    /** Admin: an email could not be sent (EmailLog). */
    public static function emailFailed(EmailLog $log): void
    {
        try {
            static::send(User::where('role', 'admin')->whereNull('suspended_at')->get(), null, 'email_failed',
                ['to' => $log->to_email, 'subject' => $log->subject], '/admin/notifications?tab=emails');
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function staff(): Collection
    {
        return User::whereIn('role', ['admin', 'editor'])->whereNull('suspended_at')->get();
    }

    private static function send(Collection $users, ?SubmissionEvent $event, string $kind, array $params, string $href): void
    {
        $users->filter()->unique('id')
            ->reject(fn (User $user) => $event && $user->id === $event->user_id) // not for your own action
            ->each(fn (User $user) => UserNotification::create([
                'user_id' => $user->id,
                'submission_id' => $event?->submission_id,
                'kind' => $kind,
                'params' => $params,
                'href' => $href,
            ]));
    }
}
