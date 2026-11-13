<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\PresentationSession;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Manage submissions" for editors and admin: browse papers, move them to another track.
 * Screening and the later workflow steps are added next.
 */
class SubmissionController extends Controller
{
    /**
     * Submissions lists the conference track only (2026-11-12): papers still at the first gate live on Selection, and
     * papers whose author went to a journal show only when that status is picked (they still present: scheduling).
     */
    private const FIRST_GATE = [SubmissionStatus::Submitted, SubmissionStatus::JournalSelected];

    public function index(Request $request): Response
    {
        $filters = $request->only('q', 'track', 'status', 'stage', 'view');
        // Old work-queue links (?queue=needReviewers, ...) open the matching tab
        if (! isset($filters['stage']) && isset(Submission::STAGE_OF_QUEUE[$request->query('queue')])) {
            $filters['stage'] = Submission::STAGE_OF_QUEUE[$request->query('queue')];
        }
        $stageCounts = Submission::stageCounts();
        // Opening the menu (no tab, no search or filter): the first tab with the editors' move, else "all" (2026-11-13)
        if (! isset($filters['stage']) && ! array_filter($request->only('q', 'track', 'status'))) {
            $filters['stage'] = collect(Submission::STAGES)->first(fn (string $key) => $stageCounts[$key]['count'] > 0) ?? 'all';
        }
        $stage = in_array($filters['stage'] ?? null, Submission::STAGES, true) ? $filters['stage'] : null;
        // The tab shown: a stage, or "all" (every paper, with the status filter)
        $filters['stage'] = $stage ?? 'all';
        $search = fn ($query, string $q) => $query->where(fn ($query) => $query
            ->where('code', 'like', "%{$q}%")
            ->orWhere('title_th', 'like', "%{$q}%")
            ->orWhere('title_en', 'like', "%{$q}%")
            ->orWhereHas('authors', fn ($query) => $query->where('name', 'like', "%{$q}%")));
        $journalPicked = ! $stage && ($filters['status'] ?? null) === SubmissionStatus::JournalAccepted->value;

        $submissions = Submission::currentYear()
            ->whereNotIn('status', $journalPicked ? self::FIRST_GATE : [...self::FIRST_GATE, SubmissionStatus::JournalAccepted])
            ->with('track', 'authors', 'reviews.reviewer:id,name')
            // This round's reviews: assigned, sent, late, cannot review
            ->withReviewCounts()
            ->withCount('notes')
            ->withRevisionCount()
            ->when($stage, fn ($query) => $query->stage($stage))
            ->when($filters['q'] ?? null, $search)
            ->when($filters['track'] ?? null, fn ($query, $track) => $query->where('track_id', $track))
            ->when(! $stage ? ($filters['status'] ?? null) : null, fn ($query, string $status) => $query->where('status', $status))
            // In a tab: the editors' move first, then authors revising (closest due date first, past ones on top)
            ->when($stage, fn ($query) => $query->orderByRaw("status IN ('screening_revision', 'review_revision'), revision_due_date IS NULL, revision_due_date"))
            ->latest()
            // Cards by track show every paper that matches, one page
            ->paginate(($filters['view'] ?? null) === 'tracks' ? 500 : 15)
            ->withQueryString()
            ->through(fn (Submission $submission) => [
                ...$submission->toClient(),
                'notes' => $submission->notes_count,
                'revisionCount' => $submission->revision_count,
                // This round's reviewers at a glance (ReviewerStatusList): sent with the recommendation, or the due date
                'reviewers' => $submission->reviews->where('round', $submission->review_round)->sortBy('id')->values()
                    ->map(fn (Review $review) => [
                        'name' => $review->reviewer?->name,
                        'state' => $review->setRelation('submission', $submission)->state(),
                        'recommendation' => $review->isSubmitted() ? $review->recommendation?->value : null,
                        'due' => $review->due_date?->toDateString(),
                    ]),
                'reviewProgress' => $submission->status === SubmissionStatus::InReview
                    ? ['total' => $submission->reviews_total, 'done' => $submission->reviews_done, 'late' => $submission->reviews_late]
                    : null,
                // Whose move it is (editor / reviewer / author) and what to do, so nobody waits on the wrong person
                'waitingOn' => $submission->waitingOn(),
            ]);

        return Inertia::render('Admin/Submissions/Index', [
            'submissions' => $submissions,
            'filters' => $filters,
            'tracks' => Track::ordered()->get()->map->toClient(),
            // Conference-track statuses, plus "went to a journal" to find papers that still present
            'statuses' => collect(SubmissionStatus::cases())->reject(fn (SubmissionStatus $s) => in_array($s, self::FIRST_GATE, true))->pluck('value')->values(),
            // A search that also matches papers still at the first gate: point to Selection
            'atFirstGate' => ($filters['q'] ?? null) ? Submission::currentYear()->whereIn('status', self::FIRST_GATE)->when($filters['q'], $search)->count() : 0,
            // Only camera-ready files the editors approved go into the proceedings ZIP
            'cameraReadyCount' => Submission::currentYear()->whereNotNull('camera_ready_path')->whereNotNull('camera_ready_approved_at')->count(),
            'stages' => $stageCounts,
            ...Submission::window(),
        ]);
    }

    public function show(Request $request, Submission $submission): Response
    {
        $submission->load('track', 'authors', 'user', 'journal');

        return Inertia::render('Admin/Submissions/Show', [
            'submission' => [...$submission->toClient(full: true), 'revisionCount' => $submission->events()->where('action', SubmissionAction::Resubmitted)->count()],
            'tracks' => Track::ordered()->get()->map->toClient(),
            // Name or email of the signed-in editor matches an author (the editor decides, nothing is blocked)
            'conflict' => $submission->conflictsWith($request->user()),
            // Staff coordinate with the author: their phone (reviewers and the author's own page never get it)
            'contactPhone' => $submission->user?->phone,
            'versions' => $submission->versionsFor('staff'),
            'maxMb' => (int) Setting::get('upload_max_mb'),
            'files' => $submission->currentFiles(),
            'notes' => $submission->notes()->with('user')->orderByDesc('pinned')->latest()->get()->map->toClient($request->user()),
            ...Submission::window(),
            'canScreen' => $submission->canBeScreened(),
            // Full history with who did what (staff only)
            'history' => $submission->events()->with('user')->get()->map->toClient(withActor: true),
            // Peer review: current round's reviewers, and who else from the track can be assigned
            'reviewRound' => $submission->review_round,
            'reviews' => $submission->currentReviews()->with('reviewer', 'remindedBy')->get()->map->toClient(withReviewer: true),
            'canAssign' => $submission->canAssignReviewers(),
            'canDecide' => $submission->canBeDecided(),
            'canStartRound' => $submission->canStartReviewRound(),
            'canSchedule' => $submission->canBeScheduled(),
            // Sessions to pick from (2026-11-12): the paper's track first, with the places left
            'sessionOptions' => $submission->canBeScheduled()
                ? PresentationSession::currentYear()->ordered()->with('room', 'track')->withCount('papers')->get()
                    ->map(fn (PresentationSession $session) => [...$session->toClient(), 'count' => $session->papers_count])
                    ->sortBy(fn ($session) => ($session['track']['id'] ?? null) === $submission->track_id ? 0 : 1)->values()
                : [],
            'journal' => SelectionController::panel($request->user(), $submission),
            'reviewerOptions' => $submission->canAssignReviewers()
                ? ReviewerAssignmentController::availableReviewers($submission)->map(fn (User $reviewer) => [
                    ...$reviewer->only('id', 'name', 'email'),
                    'conflict' => $submission->conflictsWith($reviewer),
                    'open' => $reviewer->open_reviews_count,
                    'late' => $reviewer->late_reviews_count,
                ])
                : [],
        ]);
    }

    /** The editor's optional file for the author with a screening or post-review decision (2026-11-12). */
    private const ATTACHMENT_MESSAGES = ['attachment.mimes' => 'validation.file_type', 'attachment.max' => 'validation.file_size', 'attachment.file' => 'validation.file_type'];

    private static function attachmentRule(): array
    {
        return ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip', 'max:'.((int) Setting::get('upload_max_mb') * 1024)];
    }

    /** Screening: one editor's decision is final for this round, and it is recorded in the history. */
    public function screen(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeScreened(), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['pass', 'revise', 'reject'])],
            'comment' => ['nullable', 'string', 'max:5000'],
            'attachment' => self::attachmentRule(),
        ], ['required' => 'validation.required', 'max' => 'validation.too_long', ...self::ATTACHMENT_MESSAGES]);

        $from = $submission->status;
        // status is not mass-assignable on purpose: only workflow actions change it
        $submission->status = SubmissionStatus::fromScreening($data['decision']);
        if ($submission->status === SubmissionStatus::InReview) {
            $submission->review_round++; // a new peer-review round starts
        }
        $submission->save();
        $file = $submission->addEditorFile($request->file('attachment'), 'screening', $request->user());
        $submission->log(SubmissionAction::Screened, $request->user(), $from, $data['comment'] ?? null, $file ? ['file' => $file] : null);

        return back()->with('success', 'submissions.screened');
    }

    /**
     * Editor's decision after review (accept / revise / reject). One editor's decision is final;
     * a revision needs a due date for the author. Reviews still open in this round are closed.
     */
    public function decide(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeDecided(), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'revise', 'reject'])],
            'comment' => ['nullable', 'string', 'max:5000'],
            'revision_due_date' => ['required_if:decision,revise', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'attachment' => self::attachmentRule(),
            // Accept and take the latest reviewed PDF as the approved camera-ready file (2026-11-13)
            'use_latest' => ['nullable', 'boolean'],
        ], [
            ...self::ATTACHMENT_MESSAGES,
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

        $file = $submission->addEditorFile($request->file('attachment'), 'decision', $request->user());
        $useLatest = $data['decision'] === 'accept' && ($data['use_latest'] ?? false);
        $meta = array_filter([
            'due' => $submission->revision_due_date?->toDateString(),
            'file' => $file,
            'final_from_latest' => $useLatest ?: null, // the author is told no file is needed
        ]);
        $submission->log(SubmissionAction::Decided, $request->user(), $from, $data['comment'] ?? null, $meta ?: null);
        if ($useLatest) {
            $submission->useLatestAsCameraReady($request->user(), withDecision: true);
        }

        return back()->with('success', 'submissions.decided');
    }

    /** Remind this paper's author (journal offer, revision or camera-ready file): one email, logged. */
    public function remindAuthor(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->authorCanBeRemindedBy($request->user()), 403);
        $submission->remindAuthor($request->user());

        return back()->with('success', ['key' => 'remindAuthor.sent', 'params' => ['code' => $submission->code]]);
    }

    /** Close a revision the author never sent: not accepted, with a reason the author reads (2026-11-13). */
    public function closeRevision(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canCloseRevision(), 403);
        $data = $request->validate(['comment' => ['required', 'string', 'max:5000']], ['required' => 'validation.required', 'max' => 'validation.too_long']);
        $submission->closeRevision($request->user(), $data['comment']);

        return back()->with('success', ['key' => 'closeRevision.done', 'params' => ['code' => $submission->code]]);
    }

    /** The "revision closed" email with the reason typed so far, shown before the editor confirms. */
    public function closeRevisionPreview(Request $request, Submission $submission): JsonResponse
    {
        abort_unless($submission->canCloseRevision(), 403);

        return response()->json($submission->previewMail(SubmissionAction::RevisionClosed, null, (string) $request->query('comment', ''), $submission->closedRevisionStatus()));
    }

    /**
     * The email an editor's decision would send, with the words typed so far (2026-11-13), shown in the confirm
     * dialog: step screen | decide | check | cancel, choice, comment, due (Y-m-d), use_latest.
     */
    public function mailPreview(Request $request, Submission $submission): JsonResponse
    {
        abort_unless($request->user()->moduleAccess('submissions') === 'full', 403);
        $q = $request->validate([
            'step' => ['required', Rule::in(['screen', 'decide', 'check', 'cancel'])],
            'choice' => ['nullable', 'string'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'due' => ['nullable', 'date_format:Y-m-d'],
            'use_latest' => ['nullable', 'boolean'],
        ]);
        $choice = (string) ($q['choice'] ?? '');
        $comment = $q['comment'] ?? null;

        [$action, $to, $meta] = match ($q['step']) {
            'screen' => [SubmissionAction::Screened, in_array($choice, ['pass', 'revise', 'reject'], true) ? SubmissionStatus::fromScreening($choice) : null, null],
            'decide' => [SubmissionAction::Decided, in_array($choice, ['accept', 'revise', 'reject'], true) ? SubmissionStatus::fromDecision($choice) : null, array_filter([
                'due' => $choice === 'revise' ? ($q['due'] ?? null) : null,
                'final_from_latest' => $choice === 'accept' && ($q['use_latest'] ?? false) ? true : null,
            ]) ?: null],
            'check' => [$choice === 'fix' ? SubmissionAction::CameraReadyFixRequested : SubmissionAction::CameraReadyApproved, null, null],
            'cancel' => [SubmissionAction::AcceptanceCancelled, null, null],
        };
        abort_if(in_array($q['step'], ['screen', 'decide'], true) && ! $to, 422);

        return response()->json($submission->previewMail($action, $meta, $comment, $to));
    }

    /** The reminder email as it would go out (recipient, subject, both texts), shown before the editor confirms. */
    public function remindAuthorPreview(Request $request, Submission $submission): JsonResponse
    {
        abort_unless($submission->authorCanBeRemindedBy($request->user()), 403);

        return response()->json($submission->reminderPreview());
    }

    /** Accepted, the author has not sent a camera-ready file yet: take the latest reviewed PDF instead (2026-11-13). */
    public function useLatestCameraReady(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->status === SubmissionStatus::Accepted, 403);
        $submission->useLatestAsCameraReady($request->user());

        return back()->with('success', 'publication.check.approved');
    }

    /** Camera-ready check (step 3 of 3): the file goes into the proceedings. */
    public function approveCameraReady(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->status === SubmissionStatus::CameraReady && ! $submission->camera_ready_approved_at, 403);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:5000']], ['max' => 'validation.too_long']);

        $submission->forceFill(['camera_ready_approved_at' => now()])->save();
        $submission->log(SubmissionAction::CameraReadyApproved, $request->user(), null, $data['comment'] ?? null);

        return back()->with('success', 'publication.check.approved');
    }

    /** Camera-ready check: back to the author for fixes (no new review); the author uploads a new file. */
    public function requestCameraReadyFix(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->status === SubmissionStatus::CameraReady && ! $submission->camera_ready_approved_at, 403);
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'attachment' => self::attachmentRule(),
        ], ['required' => 'validation.required', 'max' => 'validation.too_long', ...self::ATTACHMENT_MESSAGES]);

        $from = $submission->status;
        $submission->status = SubmissionStatus::Accepted;
        $submission->save();
        $file = $submission->addEditorFile($request->file('attachment'), 'cameraReady', $request->user());
        $submission->log(SubmissionAction::CameraReadyFixRequested, $request->user(), $from, $data['comment'], $file ? ['file' => $file] : null);

        return back()->with('success', 'publication.check.fixRequested');
    }

    /** Rare: withdraw an acceptance (plagiarism found, the author withdrew, no camera-ready...). The reason is required. */
    public function cancelAcceptance(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless(in_array($submission->status, [SubmissionStatus::Accepted, SubmissionStatus::CameraReady], true), 403);
        $data = $request->validate(['comment' => ['required', 'string', 'max:5000']], ['required' => 'validation.required', 'max' => 'validation.too_long']);

        $from = $submission->status;
        $submission->status = SubmissionStatus::ReviewRejected;
        $submission->camera_ready_approved_at = null;
        $submission->save();
        $submission->log(SubmissionAction::AcceptanceCancelled, $request->user(), $from, $data['comment']);

        return back()->with('success', 'publication.check.cancelled');
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

        $submission->setTrack($request->user(), (int) $data['track_id']);

        return back()->with('success', 'submissions.trackChanged');
    }

    /** Download the paper: its author, submission managers, the journal role, or a reviewer assigned to it. */
    public function file(Request $request, Submission $submission): StreamedResponse
    {
        abort_unless(self::canReadPaper($request->user(), $submission), 404);

        return Storage::download($submission->file_path, $submission->file_name);
    }

    /**
     * Every camera-ready file in one ZIP for compiling the Proceeding, named by paper code and first author,
     * with an index (index.csv: code, titles, authors, track, file name).
     */
    public function cameraReadyZip(): BinaryFileResponse
    {
        $papers = Submission::currentYear()->with('authors', 'track')->whereNotNull('camera_ready_path')->whereNotNull('camera_ready_approved_at')->orderBy('code')->get();
        abort_if($papers->isEmpty(), 404);

        $path = tempnam(sys_get_temp_dir(), 'skdiit-zip');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);

        $index = fopen('php://temp', 'r+');
        fwrite($index, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Thai correctly
        fputcsv($index, ['code', 'title_th', 'title_en', 'authors', 'track', 'file']);

        foreach ($papers as $paper) {
            $ext = pathinfo($paper->camera_ready_name, PATHINFO_EXTENSION) ?: 'pdf';
            $name = $paper->code.'-'.(Str::slug(Str::limit($paper->authors->first()?->name ?? '', 30, ''), '-', 'th') ?: 'paper').'.'.$ext;
            $zip->addFile(Storage::path($paper->camera_ready_path), $name);
            fputcsv($index, [$paper->code, $paper->title_th, $paper->title_en, $paper->authors->pluck('name')->implode(', '), $paper->track?->name_th, $name]);
        }

        rewind($index);
        $zip->addFromString('index.csv', stream_get_contents($index));
        fclose($index);
        $zip->close();

        return response()->download($path, 'skdiit2027-camera-ready-'.now()->format('Ymd-His').'.zip')->deleteFileAfterSend();
    }

    /** One stored file. Reviewers only get manuscripts (PDF) and response letters, never Word, Turnitin or supplementary files. */
    public function versionFile(Request $request, SubmissionFile $file): StreamedResponse
    {
        $user = $request->user();
        $submission = $file->submission;
        $isReviewerOnly = $submission->user_id !== $user->id && ! $user->moduleAccess('submissions')
            && ! $user->moduleAccess('selection');
        abort_unless(self::canReadPaper($user, $submission) && (! $isReviewerOnly || in_array($file->kind, ['manuscript', 'response'], true)), 404);

        return $request->boolean('inline') && $file->isPdf()
            ? Storage::response($file->path, $file->name)
            : Storage::download($file->path, $file->name);
    }

    public function cameraReady(Request $request, Submission $submission): StreamedResponse
    {
        $user = $request->user();
        abort_unless($submission->camera_ready_path && ($submission->user_id === $user->id || $user->moduleAccess('submissions')), 404);

        return Storage::download($submission->camera_ready_path, $submission->camera_ready_name);
    }

    /** Presentation slides: their author or submission managers. */
    public function slides(Request $request, Submission $submission): StreamedResponse
    {
        $user = $request->user();
        abort_unless($submission->slides_path && ($submission->user_id === $user->id || $user->moduleAccess('submissions')), 404);

        return Storage::download($submission->slides_path, $submission->slides_name);
    }

    /** The paper's files: its author, submission managers, the journal role (every paper), or an assigned reviewer. */
    public static function canReadPaper(User $user, Submission $submission): bool
    {
        return $submission->user_id === $user->id
            || $user->moduleAccess('submissions')
            || $user->moduleAccess('selection') // admin, editors and the journal role see every paper at the first gate
            || ($submission->year === Submission::conferenceYear() && $submission->reviews()->where('reviewer_id', $user->id)->exists());
    }
}
