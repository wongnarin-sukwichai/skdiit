<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewRecommendation;
use App\Enums\SubmissionAction;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My reviews" for reviewers: papers assigned to them and the evaluation form.
 * Reviewers see the paper and its authors, but never the other reviewers.
 */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $reviews = Review::where('reviewer_id', $request->user()->id)
            ->with('submission.track')
            ->orderByRaw('submitted_at IS NOT NULL') // open work first
            ->orderBy('due_date')
            ->get()
            ->map(fn (Review $review) => [
                ...$review->toClient(withReviewer: false),
                'paper' => [
                    'code' => $review->submission->code,
                    'title' => ['en' => $review->submission->title_en, 'th' => $review->submission->title_th],
                    'track' => $review->submission->track->toClient(),
                ],
            ]);

        return Inertia::render('Admin/Reviews/Index', ['reviews' => $reviews]);
    }

    public function show(Request $request, Review $review): Response
    {
        $this->authorizeReviewer($request, $review);

        $submission = $review->submission->load('track', 'authors', 'user');

        return Inertia::render('Admin/Reviews/Show', [
            'review' => $review->toClient(withReviewer: false),
            'submission' => $submission->toClient(full: true),
            'recommendations' => array_column(ReviewRecommendation::cases(), 'value'),
        ]);
    }

    /** Submitting is final: the evaluation goes to the editors and cannot be edited afterwards. */
    public function submit(Request $request, Review $review): RedirectResponse
    {
        $this->authorizeReviewer($request, $review);
        abort_if($review->isSubmitted() || $review->isClosed(), 403);

        $data = $request->validate([
            'recommendation' => ['required', Rule::enum(ReviewRecommendation::class)],
            'comment' => ['required', 'string', 'max:20000'],
        ], ['required' => 'validation.required', 'max' => 'validation.too_long']);

        $review->forceFill([...$data, 'submitted_at' => now()])->save();
        $review->submission->log(SubmissionAction::ReviewSubmitted, $request->user(), meta: [
            'recommendation' => $data['recommendation'],
        ]);

        return back()->with('success', 'reviews.submitted');
    }

    private function authorizeReviewer(Request $request, Review $review): void
    {
        abort_unless($review->reviewer_id === $request->user()->id, 404);
    }
}
