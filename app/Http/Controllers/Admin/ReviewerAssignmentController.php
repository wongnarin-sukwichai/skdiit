<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin and editors assign reviewers to a paper in peer review, change due dates, and remove
 * a reviewer who declined outside the system (reviewers cannot decline in the app).
 */
class ReviewerAssignmentController extends Controller
{
    private const MESSAGES = [
        'required' => 'validation.required',
        'due_date.date_format' => 'validation.date',
        'due_date.after_or_equal' => 'validation.due_past',
        'reviewer_id.in' => 'validation.reviewer_track',
    ];

    public function store(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canAssignReviewers(), 403);

        $data = $request->validate([
            // Only reviewers of the paper's track who are not already on this round
            'reviewer_id' => ['required', 'integer', Rule::in(self::availableReviewers($submission)->pluck('id'))],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], self::MESSAGES);

        $review = $submission->reviews()->create([
            ...$data,
            'assigned_by' => $request->user()->id,
            'round' => $submission->review_round,
        ]);

        $submission->log(SubmissionAction::ReviewerAssigned, $request->user(), meta: [
            'reviewer' => $review->reviewer->name,
            'due' => $data['due_date'],
        ]);

        return back()->with('success', 'reviews.assigned');
    }

    public function update(Request $request, Submission $submission, Review $review): RedirectResponse
    {
        $this->ensureEditable($submission, $review);

        $data = $request->validate([
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], self::MESSAGES);

        $from = $review->due_date->toDateString();
        $review->update($data);

        if ($from !== $data['due_date']) {
            $submission->log(SubmissionAction::ReviewDueChanged, $request->user(), meta: [
                'reviewer' => $review->reviewer->name,
                'from' => $from,
                'to' => $data['due_date'],
            ]);
        }

        return back()->with('success', 'admin.settings.saved');
    }

    public function destroy(Request $request, Submission $submission, Review $review): RedirectResponse
    {
        $this->ensureEditable($submission, $review);

        $name = $review->reviewer->name;
        $review->delete();
        $submission->log(SubmissionAction::ReviewerRemoved, $request->user(), meta: ['reviewer' => $name]);

        return back()->with('success', 'reviews.removed');
    }

    /**
     * Reviewers who may be assigned: in the paper's track and not yet on the current round.
     * Each carries a conflict flag (name or email matches an author); it is a warning only.
     */
    public static function availableReviewers(Submission $submission)
    {
        $assigned = $submission->currentReviews()->pluck('reviewer_id');

        return User::where('role', 'reviewer')
            ->whereHas('tracks', fn ($query) => $query->whereKey($submission->track_id))
            ->whereNotIn('id', $assigned)
            ->orderBy('name')
            ->get();
    }

    /** Only open (not yet submitted) assignments of the current round can be changed or removed. */
    private function ensureEditable(Submission $submission, Review $review): void
    {
        abort_unless($review->submission_id === $submission->id, 404);
        abort_if($review->isSubmitted() || ! $submission->canAssignReviewers() || $review->round !== $submission->review_round, 403);
    }
}
