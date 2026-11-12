<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;
use App\Enums\ReviewRecommendation;
use App\Enums\SubmissionAction;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewCriterion;
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
                'outcome' => $review->outcomeForReviewer(),
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
            'review' => [...$review->toClient(withReviewer: false), 'outcome' => $review->outcomeForReviewer()],
            'submission' => $submission->toClient(full: true),
            'recommendations' => array_column(ReviewRecommendation::cases(), 'value'),
            // What to score from 1 to 5 (Settings › Review criteria)
            'criteria' => ReviewCriterion::ordered()->get()->map->toClient(),
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
        $scoreRules = $this->scoreRules(required: true);
        $data = $request->validate([
            ...$scoreRules,
            'recommendation' => ['required', Rule::enum(ReviewRecommendation::class)],
            // The comment to the author is optional (2026-11-12): scores and the recommendation are what is required
            'comment' => ['nullable', 'string', 'max:20000'],
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
            'scores.*.required' => 'reviews.form.scoreRequired',
            'scores.*.between' => 'reviews.form.scoreRequired',
        ]);

        $file = $request->file('attachment');
        $review->saveScores($data['scores'] ?? []);
        unset($data['attachment'], $data['no_conflict'], $data['scores']);
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
            ...$this->scoreRules(required: false),
            'recommendation' => ['nullable', Rule::enum(ReviewRecommendation::class)],
            'comment' => ['nullable', 'string', 'max:20000'],
            'comment_to_editor' => ['nullable', 'string', 'max:20000'],
        ], ['max' => 'validation.too_long']);

        $review->saveScores($data['scores'] ?? []);
        unset($data['scores']);
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

    /**
     * The reviewer's attachment: that reviewer and the editors/admin, and (2026-11-12) the paper's author once the
     * round is decided, unless an editor held it back. The author gets it renamed "Reviewer N's notes".
     */
    public function attachment(Request $request, Review $review): StreamedResponse
    {
        $user = $request->user();
        abort_unless((bool) $review->attachment_path, 404);
        if ($review->reviewer_id === $user->id || $user->moduleAccess('submissions')) {
            return Storage::download($review->attachment_path, $review->attachment_name);
        }

        $paper = $review->submission;
        $decided = $paper->status === SubmissionStatus::InReview ? $review->round < $paper->review_round : $review->round <= $paper->review_round;
        abort_unless($paper->user_id === $user->id && $review->attachment_to_author && $review->isSubmitted() && $decided, 404);

        return Storage::download($review->attachment_path, Review::authorFileName($review->numberForAuthor(), $review->attachment_name));
    }

    /** Editors pass the reviewer's file on to the author (the default) or hold it back. */
    public function attachmentToAuthor(Request $request, Review $review): RedirectResponse
    {
        abort_unless((bool) $review->attachment_path, 404);
        $data = $request->validate(['to_author' => ['required', 'boolean']]);
        $review->forceFill(['attachment_to_author' => $data['to_author']])->save();

        return back()->with('success', $data['to_author'] ? 'reviews.toast.fileShared' : 'reviews.toast.fileHeld');
    }

    /** One score (1-5) per criterion: all required to send, any subset for a draft. */
    private function scoreRules(bool $required): array
    {
        $rules = ['scores' => ['nullable', 'array']];
        foreach (ReviewCriterion::pluck('id') as $id) {
            $rules["scores.{$id}"] = [$required ? 'required' : 'nullable', 'integer', 'between:'.ReviewCriterion::MIN.','.ReviewCriterion::MAX];
        }

        return $rules;
    }

    private function authorizeReviewer(Request $request, Review $review): void
    {
        abort_unless($review->reviewer_id === $request->user()->id, 404);
    }
}
