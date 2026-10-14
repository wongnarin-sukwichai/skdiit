<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home page per role. The paper numbers are real (editor and admin); the rest is still mock
 * (resources/js/data/mock.js: dashboardData) until those modules are wired.
 * Editors also coordinate with authors and reviewers (there is no secretariat role), so they get
 * the paper activity with who did it, and reviewers who never signed in.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $request->user()->accessRole();

        return Inertia::render('Admin/Dashboard', [
            'papers' => in_array($role, ['admin', 'editor'], true) ? $this->papers() : null,
            'mine' => $role === 'reviewer' ? $this->reviewerWork($request->user()) : null,
            'myPapers' => $role === 'author' ? $this->authorPapers($request->user()) : null,
        ]);
    }

    /** An author's papers, their turn first, with the next step in the author's terms. */
    private function authorPapers(User $author): array
    {
        return $author->submissions()->withReviewCounts()->latest()->get()
            ->map(fn (Submission $paper) => [
                'id' => $paper->id,
                'code' => $paper->code,
                'title' => ['en' => $paper->title_en, 'th' => $paper->title_th],
                'status' => $paper->status->value,
                'next' => $paper->nextForAuthor(),
            ])
            ->sortByDesc(fn ($paper) => $paper['next']['mine'])
            ->values()
            ->all();
    }

    /** A reviewer's own work: open evaluations by due date, and how many are sent. */
    private function reviewerWork(User $reviewer): array
    {
        $open = Review::where('reviewer_id', $reviewer->id)->whereNull('submitted_at')->with('submission')->orderBy('due_date')->get()
            ->reject(fn (Review $review) => $review->isClosed());
        $soon = today()->addDays(3);

        return [
            'kpis' => [
                'pendingReviews' => $open->reject->isUnavailable()->count(),
                'dueSoon' => $open->filter(fn (Review $r) => ! $r->isOverdue() && ! $r->isUnavailable() && $r->due_date->lte($soon))->count(),
                'overdueMine' => $open->filter(fn (Review $r) => $r->isOverdue() && ! $r->isUnavailable())->count(),
                'doneReviews' => Review::where('reviewer_id', $reviewer->id)->whereNotNull('submitted_at')->count(),
            ],
            'reviews' => $open->map(fn (Review $review) => [
                'id' => $review->id,
                'code' => $review->submission->code,
                'title' => ['en' => $review->submission->title_en, 'th' => $review->submission->title_th],
                'due' => $review->due_date->toDateString(),
                'state' => $review->isUnavailable() ? 'unavailable' : ($review->isOverdue() ? 'overdue' : ($review->due_date->lte($soon) ? 'dueSoon' : 'pending')),
                'draft' => (bool) $review->draft_saved_at,
            ])->values(),
        ];
    }

    private function papers(): array
    {
        $overdue = Review::query()
            ->with('reviewer', 'submission', 'remindedBy')
            ->whereNull('submitted_at')
            ->whereDate('due_date', '<', today())
            ->whereHas('submission', fn ($paper) => $paper->where('status', SubmissionStatus::InReview)->whereColumn('submissions.review_round', 'reviews.round'))
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $quietReviewers = User::where('role', 'reviewer')->whereNull('email_verified_at')->whereNull('suspended_at')->orderBy('name')->get(['id', 'name', 'email']);

        return [
            'queues' => Submission::queueCounts(),
            'journalWaiting' => Submission::where('status', SubmissionStatus::OptionB)->count(),
            'overdueReviews' => $overdue->count(),
            'overdue' => $overdue->map(fn (Review $review) => [
                'reviewer' => $review->reviewer?->name,
                'code' => $review->submission->code,
                'submissionId' => $review->submission_id,
                'days' => (int) $review->due_date->diffInDays(today()),
                // Who already chased this reviewer, so nobody repeats it
                'reminder' => $review->reminded_at ? ['at' => $review->reminded_at->toIso8601String(), 'by' => $review->remindedBy?->name] : null,
            ]),
            'reviewersNotSignedIn' => $quietReviewers->count(),
            // Latest paper activity with who did it (staff view; authors never see editor names)
            'activity' => SubmissionEvent::with('user', 'submission:id,code')->latest()->latest('id')->limit(10)->get()
                ->map(fn (SubmissionEvent $event) => [
                    'id' => $event->id,
                    'action' => $event->action->value,
                    'toStatus' => $event->to_status,
                    'meta' => $event->meta,
                    'code' => $event->submission?->code,
                    'submissionId' => $event->submission_id,
                    'actor' => $event->user ? ['name' => $event->user->name, 'role' => $event->user->accessRole()] : null,
                    'at' => $event->created_at->toIso8601String(),
                ]),
        ];
    }
}
