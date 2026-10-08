<?php

namespace App\Http\Controllers\Admin;

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
use Illuminate\Validation\Rule;
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
        'track_id.exists' => 'validation.required',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/MySubmissions/Index', [
            'submissions' => $request->user()->submissions()->with('track', 'authors')->latest()->get()
                ->map(fn (Submission $submission) => [
                    ...$submission->toClient(),
                    'canEdit' => $submission->canBeEditedByAuthor(),
                ]),
            ...$this->window(),
        ]);
    }

    public function create(): Response
    {
        abort_unless(Submission::isOpen(), 403);

        return Inertia::render('Admin/MySubmissions/Form', $this->formProps());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Submission::isOpen(), 403);

        $data = $this->validated($request, fileRequired: true);

        $submission = DB::transaction(function () use ($request, $data) {
            $submission = $request->user()->submissions()->create([
                ...$data['fields'],
                ...$this->storeFile($request),
            ]);
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
            // Authors see decisions and comments, but not which editor made them
            'history' => $submission->events()->get()
                ->filter(fn (SubmissionEvent $event) => $event->action->visibleToAuthor())
                ->map->toClient(withActor: false)
                ->values(),
            // Reviewer comments of decided rounds, as "Reviewer 1", "Reviewer 2", ...
            'reviewFeedback' => $submission->reviewsForAuthor(),
            'maxMb' => (int) Setting::get('upload_max_mb'),
            ...$this->window(),
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
                ...$submission->only('track_id', 'title_th', 'title_en', 'abstract_th', 'abstract_en', 'keywords_th', 'keywords_en'),
                'authors' => $submission->authors->map->only('name', 'affiliation', 'email')->values(),
                'file' => ['name' => $submission->file_name, 'size' => $submission->file_size, 'url' => "/admin/submissions/{$submission->id}/file"],
            ],
        ]);
    }

    public function update(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($request, $submission);
        abort_unless($submission->canBeEditedByAuthor(), 403);

        $data = $this->validated($request, fileRequired: false);

        DB::transaction(function () use ($request, $submission, $data) {
            $old = $submission->file_path;
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
                $submission->status = $from === SubmissionStatus::Revision ? SubmissionStatus::Submitted : SubmissionStatus::Revised;
            }
            $submission->save();
            $submission->authors()->delete();
            $submission->authors()->createMany($data['authors']);

            $resubmit
                ? $submission->log(SubmissionAction::Resubmitted, $request->user(), $from)
                : $submission->log(SubmissionAction::Updated, $request->user());

            if ($file) {
                Storage::delete($old);
            }
        });

        return redirect("/admin/my-submissions/{$submission->id}")
            ->with('success', $submission->wasChanged('status') ? 'submissions.resubmitted' : 'admin.settings.saved');
    }

    /** After acceptance the author picks A (Proceeding) or B (journal, coordinated by the editors). Final. */
    public function chooseOption(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($request, $submission);
        abort_unless($submission->status === SubmissionStatus::Accepted, 403);

        $data = $request->validate(['option' => ['required', Rule::in(['a', 'b'])]], self::MESSAGES);

        $from = $submission->status;
        $submission->publish_option = $data['option'];
        $submission->status = $data['option'] === 'a' ? SubmissionStatus::OptionA : SubmissionStatus::OptionB;
        $submission->save();
        $submission->log(SubmissionAction::OptionChosen, $request->user(), $from, meta: ['option' => $data['option']]);

        return back()->with('success', 'publication.optionSaved');
    }

    /** Option A: upload the camera-ready file; it can be replaced until the paper moves on. */
    public function uploadCameraReady(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($request, $submission);
        abort_unless(in_array($submission->status, [SubmissionStatus::OptionA, SubmissionStatus::CameraReady], true), 403);

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

    private function authorizeOwner(Request $request, Submission $submission): void
    {
        abort_unless($submission->user_id === $request->user()->id, 404);
    }

    /** Deadline info shown on the author pages. */
    private function window(): array
    {
        return [
            'deadline' => Submission::deadline()?->toIso8601String(),
            'isOpen' => Submission::isOpen(),
        ];
    }

    private function formProps(): array
    {
        return [
            'tracks' => Track::ordered()->get()->map->toClient(),
            'maxMb' => (int) Setting::get('upload_max_mb'),
            ...$this->window(),
        ];
    }

    /** @return array{fields: array<string, mixed>, authors: list<array<string, mixed>>} */
    private function validated(Request $request, bool $fileRequired): array
    {
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
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.((int) Setting::get('upload_max_mb') * 1024)],
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

    /** Stores the uploaded paper on the private disk. */
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
