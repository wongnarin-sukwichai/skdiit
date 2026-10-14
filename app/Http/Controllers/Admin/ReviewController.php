<?php

namespace App\Http\Controllers\Admin;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;
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
            'maxMb' => (int) Setting::get('upload_max_mb'),
            // Earlier versions and the author's answers (a later round compares with the previous version)
            'versions' => $submission->versionsFor('reviewer'),
        ]);
    }

    /** Submitting is final: the evaluation goes to the editors and cannot be edited afterwards. */
    public function submit(Request $request, Review $review): RedirectResponse
    {
        $this->authorizeReviewer($request, $review);
        abort_if($review->isSubmitted() || $review->isClosed() || $review->isUnavailable(), 403);

        $maxMb = (int) Setting::get('upload_max_mb');
        $data = $request->validate([
            'recommendation' => ['required', Rule::enum(ReviewRecommendation::class)],
            'comment' => ['required', 'string', 'max:20000'],
            'comment_to_editor' => ['nullable', 'string', 'max:20000'],
            // Declared with every evaluation: no conflict of interest with this paper
            'no_conflict' => ['accepted'],
            // Optional: e.g. the paper with comments written on it
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.($maxMb * 1024)],
        ], [
            'required' => 'validation.required',
            'comment.max' => 'validation.too_long',
            'comment_to_editor.max' => 'validation.too_long',
            'no_conflict.accepted' => 'reviews.form.conflictRequired',
            'attachment.mimes' => 'validation.file_type',
            'attachment.max' => 'validation.file_size',
        ]);

        $file = $request->file('attachment');
        unset($data['attachment'], $data['no_conflict']);
        $review->forceFill([
            ...$data,
            ...($file ? ['attachment_path' => $file->store('reviews'), 'attachment_name' => $file->getClientOriginalName(), 'attachment_size' => $file->getSize()] : []),
            'submitted_at' => now(),
            'conflict_declared_at' => now(),
        ])->save();
        $review->submission->log(SubmissionAction::ReviewSubmitted, $request->user(), meta: [
            'recommendation' => $data['recommendation'],
        ]);

        return back()->with('success', 'reviews.submitted');
    }

    /** Keep working later: the evaluation is saved but not sent (editors do not see a draft). */
    public function draft(Request $request, Review $review): RedirectResponse
    {
        $this->authorizeReviewer($request, $review);
        abort_if($review->isSubmitted() || $review->isClosed() || $review->isUnavailable(), 403);

        $data = $request->validate([
            'recommendation' => ['nullable', Rule::enum(ReviewRecommendation::class)],
            'comment' => ['nullable', 'string', 'max:20000'],
            'comment_to_editor' => ['nullable', 'string', 'max:20000'],
        ], ['max' => 'validation.too_long']);

        $review->forceFill([...$data, 'draft_saved_at' => now()])->save();

        return back()->with('success', 'reviews.form.draftSaved');
    }

    /** "I cannot review this": the editors see it at once and assign someone else. */
    public function unavailable(Request $request, Review $review): RedirectResponse
    {
        $this->authorizeReviewer($request, $review);
        abort_if($review->isSubmitted() || $review->isClosed() || $review->isUnavailable(), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['required' => 'validation.required', 'max' => 'validation.too_long']);

        $review->forceFill(['unavailable_at' => now(), 'unavailable_reason' => trim($data['reason'])])->save();
        $review->submission->log(SubmissionAction::ReviewUnavailable, $request->user(), meta: ['reviewer' => $request->user()->name, 'reason' => trim($data['reason'])]);

        return back()->with('success', 'reviews.unavailable.sent');
    }

    /** The reviewer's attachment: for that reviewer and the editors/admin only (authors do not get it: files can carry names). */
    public function attachment(Request $request, Review $review): StreamedResponse
    {
        $user = $request->user();
        abort_unless($review->attachment_path && ($review->reviewer_id === $user->id || $user->moduleAccess('submissions')), 404);

        return Storage::download($review->attachment_path, $review->attachment_name);
    }

    private function authorizeReviewer(Request $request, Review $review): void
    {
        abort_unless($review->reviewer_id === $request->user()->id, 404);
    }
}
