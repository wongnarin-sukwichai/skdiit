<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Manage submissions" for editors and admin: browse papers, move them to another track.
 * Screening and the later workflow steps are added next.
 */
class SubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only('q', 'track', 'status');

        $submissions = Submission::query()
            ->with('track', 'authors')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('title_th', 'like', "%{$q}%")
                ->orWhere('title_en', 'like', "%{$q}%")
                ->orWhereHas('authors', fn ($query) => $query->where('name', 'like', "%{$q}%"))))
            ->when($filters['track'] ?? null, fn ($query, $track) => $query->where('track_id', $track))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Submission $submission) => $submission->toClient());

        return Inertia::render('Admin/Submissions/Index', [
            'submissions' => $submissions,
            'filters' => $filters,
            'tracks' => Track::ordered()->get()->map->toClient(),
            'statuses' => array_column(SubmissionStatus::cases(), 'value'),
            'optionBCount' => Submission::where('status', SubmissionStatus::OptionB)->count(),
            'deadline' => Submission::deadline()?->toIso8601String(),
            'isOpen' => Submission::isOpen(),
        ]);
    }

    public function show(Request $request, Submission $submission): Response
    {
        $submission->load('track', 'authors', 'user');

        return Inertia::render('Admin/Submissions/Show', [
            'submission' => $submission->toClient(full: true),
            'tracks' => Track::ordered()->get()->map->toClient(),
            // Name or email of the signed-in editor matches an author (the editor decides, nothing is blocked)
            'conflict' => $submission->conflictsWith($request->user()),
            'deadline' => Submission::deadline()?->toIso8601String(),
            'isOpen' => Submission::isOpen(),
            'canScreen' => $submission->canBeScreened(),
            // Full history with who did what (staff only)
            'history' => $submission->events()->with('user')->get()->map->toClient(withActor: true),
            // Peer review: current round's reviewers, and who else from the track can be assigned
            'reviewRound' => $submission->review_round,
            'reviews' => $submission->currentReviews()->with('reviewer')->get()->map->toClient(withReviewer: true),
            'canAssign' => $submission->canAssignReviewers(),
            'canDecide' => $submission->canBeDecided(),
            'canStartRound' => $submission->canStartReviewRound(),
            'canSchedule' => $submission->status->canBeScheduled(),
            'reviewerOptions' => $submission->canAssignReviewers()
                ? ReviewerAssignmentController::availableReviewers($submission)->map(fn (User $reviewer) => [
                    ...$reviewer->only('id', 'name', 'email'),
                    'conflict' => $submission->conflictsWith($reviewer),
                ])
                : [],
        ]);
    }

    /** Screening: one editor's decision is final for this round, and it is recorded in the history. */
    public function screen(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeScreened(), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['pass', 'revise', 'reject'])],
            'comment' => ['nullable', 'string', 'max:5000'],
        ], ['required' => 'validation.required', 'max' => 'validation.too_long']);

        $from = $submission->status;
        // status is not mass-assignable on purpose: only workflow actions change it
        $submission->status = SubmissionStatus::fromScreening($data['decision']);
        if ($submission->status === SubmissionStatus::InReview) {
            $submission->review_round++; // a new peer-review round starts
        }
        $submission->save();
        $submission->log(SubmissionAction::Screened, $request->user(), $from, $data['comment'] ?? null);

        return back()->with('success', 'submissions.screened');
    }

    /**
     * Editor's decision after review (accept / minor / major / reject). One editor's decision is final;
     * a revision needs a due date for the author. Reviews still open in this round are closed.
     */
    public function decide(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeDecided(), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'minor', 'major', 'reject'])],
            'comment' => ['nullable', 'string', 'max:5000'],
            'revision_due_date' => ['required_if:decision,minor,major', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'required' => 'validation.required',
            'required_if' => 'validation.required',
            'max' => 'validation.too_long',
            'date_format' => 'validation.date',
            'after_or_equal' => 'validation.due_past',
        ]);

        $from = $submission->status;
        $submission->status = SubmissionStatus::fromDecision($data['decision']);
        $submission->revision_due_date = $submission->status->awaitsAuthorRevision() ? $data['revision_due_date'] : null;
        $submission->save();

        $submission->log(SubmissionAction::Decided, $request->user(), $from, $data['comment'] ?? null,
            $submission->revision_due_date ? ['due' => $submission->revision_due_date->toDateString()] : null);

        return back()->with('success', 'submissions.decided');
    }

    /** Send a revised paper to reviewers again: a new review round starts with no reviewers yet. */
    public function startReviewRound(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canStartReviewRound(), 403);

        $from = $submission->status;
        $submission->status = SubmissionStatus::InReview;
        $submission->review_round++;
        $submission->save();

        $submission->log(SubmissionAction::ReviewRoundStarted, $request->user(), $from, meta: ['round' => $submission->review_round]);

        return back()->with('success', 'submissions.roundStarted');
    }

    public function updateTrack(Request $request, Submission $submission): RedirectResponse
    {
        $data = $request->validate(['track_id' => ['required', 'integer', 'exists:tracks,id']]);

        $from = $submission->track;
        $submission->update($data);

        if ($submission->wasChanged('track_id')) {
            $submission->log(SubmissionAction::TrackChanged, $request->user(), meta: [
                'from' => $from->toClient()['name'],
                'to' => $submission->load('track')->track->toClient()['name'],
            ]);
        }

        return back()->with('success', 'submissions.trackChanged');
    }

    /** Download the paper: its author, submission managers, or a reviewer assigned to it. */
    public function file(Request $request, Submission $submission): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $submission->user_id === $user->id
                || $user->moduleAccess('submissions')
                || $submission->reviews()->where('reviewer_id', $user->id)->exists(),
            404
        );

        return Storage::download($submission->file_path, $submission->file_name);
    }

    /** Download the camera-ready file (Option A): its author or submission managers. */
    public function cameraReady(Request $request, Submission $submission): StreamedResponse
    {
        $user = $request->user();
        abort_unless($submission->camera_ready_path && ($submission->user_id === $user->id || $user->moduleAccess('submissions')), 404);

        return Storage::download($submission->camera_ready_path, $submission->camera_ready_name);
    }
}
