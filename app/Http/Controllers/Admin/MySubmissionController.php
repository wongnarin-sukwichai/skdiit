<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Validation\ValidationException;
use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\SubmissionEvent;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My submissions" for authors: submit papers and edit them until the submission deadline.
 */
class MySubmissionController extends Controller
{
    /** Validation messages are translation keys; the Vue client renders them in the active language. */
    private const MESSAGES = [
        'required' => 'validation.required',
        'email' => 'validation.email',
        'max' => 'validation.too_long',
        'authors.min' => 'validation.authors_min',
        'file.mimes' => 'validation.file_type',
        'file.max' => 'validation.file_size',
        'file.uploaded' => 'validation.file_size',
        'word_file.mimes' => 'validation.file_type',
        'word_file.max' => 'validation.file_size',
        'word_file.uploaded' => 'validation.file_size',
        'turnitin_file.mimes' => 'validation.file_type',
        'turnitin_file.max' => 'validation.file_size',
        'turnitin_file.uploaded' => 'validation.file_size',
        'supplementary_files.max' => 'validation.too_many_files',
        'supplementary_files.*.mimes' => 'validation.file_type',
        'supplementary_files.*.max' => 'validation.file_size',
        'supplementary_files.*.uploaded' => 'validation.file_size',
        'response_file.mimes' => 'validation.file_type',
        'response_file.max' => 'validation.file_size',
        'response_file.uploaded' => 'validation.file_size',
        'track_id.exists' => 'validation.required',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/MySubmissions/Index', [
            'submissions' => $request->user()->submissions()->with('track', 'authors')->withReviewCounts()->latest()->get()
                ->map(fn (Submission $submission) => [
                    ...$submission->toClient(),
                    'canEdit' => $submission->canBeEditedByAuthor(),
                    'next' => $submission->nextForAuthor(),
                ]),
            ...Submission::window(),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        if (! Submission::isOpen()) {
            return $this->outsideWindow();
        }

        return Inertia::render('Admin/MySubmissions/Form', $this->formProps());
    }

    public function store(Request $request): RedirectResponse
    {
        // The period may have ended while the author was filling in the form
        if (! Submission::isOpen()) {
            return $this->outsideWindow();
        }

        // A new paper brings all three files: PDF, Word and the Turnitin report
        $data = $this->validated($request, fileRequired: true, turnitinRequired: true);

        $submission = DB::transaction(function () use ($request, $data) {
            $stored = $this->storeFile($request);
            $submission = $request->user()->submissions()->create([...$data['fields'], ...$stored]);
            $version = $submission->addManuscript($stored, 'initial', $request->user());
            $submission->addExtraFiles($this->extraFiles($request), $version, 'initial', $request->user());
            $submission->authors()->createMany($data['authors']);
            $submission->log(SubmissionAction::Submitted, $request->user());

            return $submission;
        });

        return redirect("/admin/my-submissions/{$submission->id}")->with('success', 'submissions.created');
    }

    public function show(Request $request, Submission $submission): Response
    {
        $this->authorizeOwner($request, $submission);

        return Inertia::render('Admin/MySubmissions/Show', [
            'submission' => [
                ...$submission->load('track', 'authors', 'user')->toClient(full: true),
                'canEdit' => $submission->canBeEditedByAuthor(),
            ],
            'files' => $submission->currentFiles(),
            // Authors see decisions and comments, but not which editor made them, nor the journal's reasons
            'history' => $submission->events()->get()
                ->filter(fn (SubmissionEvent $event) => $event->action->visibleToAuthor())
                ->map(fn (SubmissionEvent $event) => [
                    ...$event->toClient(withActor: false),
                    ...($event->action->hidesCommentFromAuthor() ? ['comment' => null] : []),
                ])
                ->values(),
            // Selected by the journal: where to submit
            'journalUrl' => Setting::get('journal_url'),
            // Reviewer comments of decided rounds, as "Reviewer 1", "Reviewer 2", ...
            'reviewFeedback' => $submission->reviewsForAuthor(),
            'versions' => $submission->versionsFor('author'),
            'maxMb' => (int) Setting::get('upload_max_mb'),
            ...Submission::window(),
        ]);
    }

    public function edit(Request $request, Submission $submission): Response
    {
        $this->authorizeOwner($request, $submission);
        abort_unless($submission->canBeEditedByAuthor(), 403);

        $submission->load('authors');

        return Inertia::render('Admin/MySubmissions/Form', [
            ...$this->formProps(),
            'submission' => [
                'id' => $submission->id,
                'code' => $submission->code,
                'status' => $submission->status->value,
                'trackLocked' => ! $submission->authorCanChangeTrack(),
                // While revising: the editor's latest message, the due date and the reviewers' comments
                'feedback' => $submission->status->awaitsAuthorRevision()
                    ? $submission->events()->whereIn('action', [SubmissionAction::Screened, SubmissionAction::Decided])->value('comment')
                    : null,
                'revisionDueDate' => $submission->revision_due_date?->toDateString(),
                'reviewFeedback' => $submission->status->awaitsAuthorRevision() ? $submission->reviewsForAuthor() : [],
                // Revising after review: answer each reviewer (or attach a response letter)
                'reviewsToAnswer' => $submission->reviewsToAnswer(),
                ...$submission->only('track_id', 'title_th', 'title_en', 'abstract_th', 'abstract_en', 'keywords_th', 'keywords_en'),
                'authors' => $submission->authors->map->only('name', 'affiliation', 'email')->values(),
                'file' => ['name' => $submission->file_name, 'size' => $submission->file_size, 'url' => "/admin/submissions/{$submission->id}/file"],
                'files' => $submission->currentFiles(),
            ],
        ]);
    }

    public function update(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($request, $submission);
        abort_unless($submission->canBeEditedByAuthor(), 403);

        // A revision must bring the revised PDF and Word files (Turnitin is optional: the last report stays);
        // after peer review it also answers the reviewers
        $data = $this->validated($request, fileRequired: $submission->status->awaitsAuthorRevision(), turnitinRequired: false);
        $answers = $this->validatedAnswers($request, $submission);

        DB::transaction(function () use ($request, $submission, $data, $answers) {
            $file = $request->hasFile('file') ? $this->storeFile($request) : [];

            $from = $submission->status;
            // A revision goes back to the editors: to screening, or to the post-review decision
            $resubmit = $from->awaitsAuthorRevision();

            $fields = $data['fields'];
            if (! $submission->authorCanChangeTrack()) {
                unset($fields['track_id']); // the editor's placement wins
            }

            $submission->fill([...$fields, ...$file]);
            if ($resubmit) {
                // Back to editor screening (the journal already passed on it), or to the post-review decision
                $submission->status = $from === SubmissionStatus::Revision ? SubmissionStatus::Screening : SubmissionStatus::Revised;
            }
            $submission->save();
            $submission->authors()->delete();
            $submission->authors()->createMany($data['authors']);

            // Keep every version: a new PDF starts a new version, the old one stays for comparing;
            // Word, Turnitin and supplementary files join the (new or current) version
            $stage = $resubmit ? 'revision' : 'edit';
            $version = $file ? $submission->addManuscript($file, $stage, $request->user()) : $submission->currentVersion();
            $submission->addExtraFiles($this->extraFiles($request), $version, $stage, $request->user());
            if ($file) {
                if ($answers['letter']) {
                    $letter = $answers['letter'];
                    $submission->files()->create([
                        'version' => $version, 'kind' => 'response', 'stage' => 'revision', 'round' => $submission->review_round,
                        'path' => $letter->store('submissions/responses'), 'name' => $letter->getClientOriginalName(), 'size' => $letter->getSize(),
                        'user_id' => $request->user()->id,
                    ]);
                }
                foreach ($answers['texts'] as $reviewId => $body) {
                    $submission->responses()->create(['version' => $version, 'review_id' => $reviewId ?: null, 'body' => $body]);
                }
            }

            $resubmit
                ? $submission->log(SubmissionAction::Resubmitted, $request->user(), $from)
                : $submission->log(SubmissionAction::Updated, $request->user());

        });

        return redirect("/admin/my-submissions/{$submission->id}")
            ->with('success', $submission->wasChanged('status') ? 'submissions.resubmitted' : 'admin.settings.saved');
    }

    /** Accepted in the conference track: upload the camera-ready file; it can be replaced later. */
    public function uploadCameraReady(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($request, $submission);
        abort_unless(in_array($submission->status, [SubmissionStatus::Accepted, SubmissionStatus::CameraReady], true), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.((int) Setting::get('upload_max_mb') * 1024)],
        ], self::MESSAGES);

        $file = $request->file('file');
        $old = $submission->camera_ready_path;
        $from = $submission->status;

        $submission->forceFill([
            'camera_ready_path' => $file->store('camera-ready'),
            'camera_ready_name' => $file->getClientOriginalName(),
            'camera_ready_size' => $file->getSize(),
            'camera_ready_at' => now(),
        ]);
        $submission->status = SubmissionStatus::CameraReady;
        $submission->save();
        $submission->log(SubmissionAction::CameraReadyUploaded, $request->user(), $from !== $submission->status ? $from : null);

        if ($old) {
            Storage::delete($old);
        }

        return back()->with('success', 'publication.cameraReadySaved');
    }

    /** Presentation slides: once the paper has a slot, replaceable until the talk (or the cut-off in Settings). */
    public function uploadSlides(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($request, $submission);
        abort_unless($submission->canUploadSlides(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,ppt,pptx', 'max:'.((int) Setting::get('upload_max_mb') * 1024)],
        ], self::MESSAGES);

        $file = $request->file('file');
        $old = $submission->slides_path;

        $submission->forceFill([
            'slides_path' => $file->store('slides'),
            'slides_name' => $file->getClientOriginalName(),
            'slides_size' => $file->getSize(),
            'slides_at' => now(),
        ])->save();
        $submission->log(SubmissionAction::SlidesUploaded, $request->user());

        if ($old) {
            Storage::delete($old);
        }

        return back()->with('success', 'publication.slides.saved');
    }

    private function authorizeOwner(Request $request, Submission $submission): void
    {
        abort_unless($submission->user_id === $request->user()->id, 404);
    }

    /** Before the opening date or after the deadline: back to the list, which shows an alert. */
    private function outsideWindow(): RedirectResponse
    {
        return redirect()->route('admin.my-submissions')->with('submissionWindow', true);
    }

    private function formProps(): array
    {
        return [
            'tracks' => Track::ordered()->get()->map->toClient(),
            'maxMb' => (int) Setting::get('upload_max_mb'),
            ...Submission::window(),
        ];
    }

    /** @return array{fields: array<string, mixed>, authors: list<array<string, mixed>>} */
    /**
     * Answers that come with a revision: one per reviewer of the decided round (required unless a response
     * letter is attached), and an optional note to the editor. Keys of 'texts': review id, or 0 for the editor.
     */
    private function validatedAnswers(Request $request, Submission $submission): array
    {
        if (! $submission->status->awaitsAuthorRevision()) {
            return ['texts' => [], 'letter' => null];
        }

        $request->validate([
            'responses' => ['nullable', 'array'],
            'responses.*' => ['nullable', 'string', 'max:10000'],
            'response_editor' => ['nullable', 'string', 'max:10000'],
            'response_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.((int) Setting::get('upload_max_mb') * 1024)],
        ], self::MESSAGES);

        $expected = collect($submission->reviewsToAnswer())->pluck('id');
        $given = collect($request->input('responses', []))->map(fn ($text) => trim((string) $text))->filter();
        if (! $request->hasFile('response_file')) {
            $missing = $expected->reject(fn ($id) => $given->has($id));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages($missing->mapWithKeys(fn ($id) => ["responses.{$id}" => 'validation.required'])->all());
            }
        }

        $texts = $given->only($expected->all())->all();
        if ($note = trim((string) $request->input('response_editor'))) {
            $texts[0] = $note;
        }

        return ['texts' => $texts, 'letter' => $request->file('response_file')];
    }

    /** Word, Turnitin and supplementary uploads of this request, for Submission::addExtraFiles(). */
    private function extraFiles(Request $request): array
    {
        return [
            'word' => $request->file('word_file'),
            'turnitin' => $request->file('turnitin_file'),
            'supplementary' => $request->file('supplementary_files', []),
        ];
    }

    /**
     * Paper details and files. The PDF ('file') and the Word file come together when required (new paper, revision);
     * the Turnitin report is required for a new paper only; supplementary files are always optional.
     */
    private function validated(Request $request, bool $fileRequired, bool $turnitinRequired): array
    {
        $max = 'max:'.((int) Setting::get('upload_max_mb') * 1024);

        $data = $request->validate([
            'track_id' => ['required', 'integer', 'exists:tracks,id'],
            'title_th' => ['required', 'string', 'max:500'],
            'title_en' => ['required', 'string', 'max:500'],
            'abstract_th' => ['required', 'string', 'max:10000'],
            'abstract_en' => ['required', 'string', 'max:10000'],
            'keywords_th' => ['required', 'string', 'max:500'],
            'keywords_en' => ['required', 'string', 'max:500'],
            'authors' => ['required', 'array', 'min:1', 'max:30'],
            'authors.*.name' => ['required', 'string', 'max:255'],
            'authors.*.affiliation' => ['required', 'string', 'max:255'],
            'authors.*.email' => ['nullable', 'email', 'max:255'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf', $max],
            'word_file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:doc,docx', $max],
            'turnitin_file' => [$turnitinRequired ? 'required' : 'nullable', 'file', 'mimes:pdf', $max],
            'supplementary_files' => ['nullable', 'array', 'max:10'],
            'supplementary_files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip', $max],
        ], self::MESSAGES);

        return [
            'fields' => collect($data)->only('track_id', 'title_th', 'title_en', 'abstract_th', 'abstract_en', 'keywords_th', 'keywords_en')->all(),
            'authors' => collect($data['authors'])->values()->map(fn (array $author, int $i) => [
                'position' => $i + 1,
                'name' => $author['name'],
                'affiliation' => $author['affiliation'],
                'email' => $author['email'] ?? null,
            ])->all(),
        ];
    }

    /** Stores the uploaded paper (PDF) on the private disk. */
    private function storeFile(Request $request): array
    {
        $file = $request->file('file');

        return [
            'file_path' => $file->store('submissions'),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ];
    }
}
