<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who an announcement goes to (item 38), within the current conference year. Suspended accounts never get one;
 * staff are not in these groups.
 */
class Audience
{
    public const KEYS = [
        'registrants', 'authors_no_paper', 'authors_with_paper',
        'authors_revision', 'authors_camera_ready', 'authors_slides',
        'reviewers', 'reviewers_open', 'reviewers_overdue',
    ];

    /** Everyone in a broad group: admin only (2026-11-13, option ก). Editors send to the to-do groups. */
    public const BROAD = ['registrants', 'authors_no_paper', 'authors_with_paper', 'reviewers'];

    /** The groups this person may send to. */
    public static function keysFor(User $user): array
    {
        return $user->accessRole() === 'admin' ? self::KEYS : array_values(array_diff(self::KEYS, self::BROAD));
    }

    public static function query(string $key): Builder
    {
        $users = User::query()->whereNull('suspended_at');
        $year = Submission::conferenceYear();
        $registrants = fn () => $users->where('role', 'user')->where('registered_year', $year);
        $paper = fn (callable $where) => $users->where('role', 'user')->whereHas('submissions', fn ($s) => $where($s->currentYear()));
        // Open evaluations: not sent, the paper still in review in that round, the reviewer has not said they cannot
        $open = fn ($review) => $review->whereNull('submitted_at')->whereNull('unavailable_at')
            ->whereHas('submission', fn ($s) => $s->currentYear()->where('status', SubmissionStatus::InReview)->whereColumn('submissions.review_round', 'reviews.round'));

        return match ($key) {
            'registrants' => $registrants(),
            // Every registrant is an author since 2026-11-13: split by whether they have sent a paper this year
            'authors_no_paper' => $registrants()->whereDoesntHave('submissions', fn ($s) => $s->currentYear()),
            'authors_with_paper' => $paper(fn ($s) => $s),
            // Their paper waits for a revision (screening or after review)
            'authors_revision' => $paper(fn ($s) => $s->whereIn('status', [SubmissionStatus::Revision, SubmissionStatus::ReviewRevision])),
            // Accepted in the conference track, camera-ready not sent yet
            'authors_camera_ready' => $paper(fn ($s) => $s->where('status', SubmissionStatus::Accepted)),
            // Presenting with a slot, slides not uploaded yet
            'authors_slides' => $paper(fn ($s) => $s->whereIn('status', [SubmissionStatus::CameraReady, SubmissionStatus::JournalAccepted])->whereNotNull('presentation_at')->whereNull('slides_path')),
            'reviewers' => $users->where('role', 'reviewer'),
            'reviewers_open' => $users->where('role', 'reviewer')->whereHas('reviews', $open),
            'reviewers_overdue' => $users->where('role', 'reviewer')->whereHas('reviews', fn ($r) => $open($r)->whereDate('due_date', '<', today())),
        };
    }

    /** People in each group now: { key: count }. */
    public static function counts(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => static::query($key)->count()])->all();
    }
}
