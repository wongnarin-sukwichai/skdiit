<?php

namespace App\Models;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Support\SiteContent;
use App\Support\WorkflowMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Submission extends Model
{
    protected $fillable = [
        'track_id', 'title_th', 'title_en', 'abstract_th', 'abstract_en', 'keywords_th', 'keywords_en',
        'file_path', 'file_name', 'file_size',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'revision_due_date' => 'date',
            'camera_ready_at' => 'datetime',
            'camera_ready_approved_at' => 'datetime',
            'slides_at' => 'datetime',
            'presentation_at' => 'datetime',
            'journal_answer_due' => 'date',
            'author_reminded_at' => 'datetime',
            'journal_reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A new paper belongs to the current conference year and is numbered within it: SKDIIT27-0001, SKDIIT28-0001, ...
        static::creating(function (Submission $submission) {
            $submission->year ??= static::conferenceYear();
        });
        static::created(fn (Submission $submission) => $submission->assignCode());
    }

    /** The next free number of the year (the highest one + 1, so a removed sample paper never makes a duplicate). */
    public function assignCode(): void
    {
        $last = static::where('year', $this->year)->whereKeyNot($this->id)->whereNotNull('code')->max('code');
        $number = $last ? (int) substr($last, -4) + 1 : 1;
        $this->forceFill(['code' => sprintf('SKDIIT%02d-%04d', $this->year % 100, $number)])->saveQuietly();
    }

    /** The conference year in use (Settings). */
    public static function conferenceYear(): int
    {
        return (int) Setting::get('conference_year');
    }

    /** Papers of the current conference year (work lists); earlier years stay for their authors and the archive. */
    public function scopeCurrentYear(Builder $query): void
    {
        $query->where('submissions.year', static::conferenceYear());
    }

    /** Finished papers of earlier conference years: the article archive. */
    public function scopeArchived(Builder $query): void
    {
        $query->where('submissions.year', '<', static::conferenceYear())
            ->whereIn('status', [SubmissionStatus::CameraReady, SubmissionStatus::JournalAccepted]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /** The partner journal the paper was offered to (first gate), kept after the author's answer. */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function authors(): HasMany
    {
        return $this->hasMany(SubmissionAuthor::class)->orderBy('position');
    }

    /** Submission deadline set by admin, or null when none is set. */
    public static function deadline(): ?Carbon
    {
        $value = Setting::get('submission_deadline');

        return $value ? Carbon::parse($value) : null;
    }

    /** Submission opening date set by admin, or null when papers are accepted right away. */
    public static function opensAt(): ?Carbon
    {
        $value = Setting::get('submission_opens');

        return $value ? Carbon::parse($value) : null;
    }

    /** The deadline has passed: every paper is locked and the journal can start selecting. */
    public static function isClosed(): bool
    {
        return (bool) static::deadline()?->isPast();
    }

    /** New papers are accepted, and authors may edit theirs, from the opening date until the deadline (all lock together). */
    public static function isOpen(): bool
    {
        return ! static::opensAt()?->isFuture() && ! static::isClosed();
    }

    /** Submission period for the client: upcoming (before opening), open, or closed (after the deadline). */
    public static function window(): array
    {
        return [
            'opensAt' => static::opensAt()?->toIso8601String(),
            'deadline' => static::deadline()?->toIso8601String(),
            'isOpen' => static::isOpen(),
            'phase' => static::isClosed() ? 'closed' : (static::isOpen() ? 'open' : 'upcoming'),
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Every uploaded manuscript version and response letter (nothing is deleted on replace). */
    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    /** The author's answers to the reviewers / editor, per version. */
    public function responses(): HasMany
    {
        return $this->hasMany(RevisionResponse::class);
    }

    /** Internal notes (admin and editors only). */
    public function notes(): HasMany
    {
        return $this->hasMany(SubmissionNote::class);
    }

    /** Record a new manuscript version (the paper's current file); returns its version number. */
    public function addManuscript(array $stored, string $stage, ?User $by): int
    {
        // review_round is null on a paper just created (the column default is not loaded), hence the (int) below
        $version = (int) $this->files()->where('kind', 'manuscript')->max('version') + 1;
        $this->files()->create([
            'version' => $version, 'kind' => 'manuscript', 'stage' => $stage, 'round' => (int) $this->review_round,
            'path' => $stored['file_path'], 'name' => $stored['file_name'], 'size' => $stored['file_size'], 'user_id' => $by?->id,
        ]);

        return $version;
    }

    /**
     * "Use the latest file as the camera-ready file" (2026-11-13, editors only): the latest manuscript PDF, the one
     * that went through review, is copied as the camera-ready file and approved at once; the author uploads nothing.
     * $withDecision: done together with "accept", so the author gets one email (decision_accept_final), not two.
     */
    public function useLatestAsCameraReady(?User $by, bool $withDecision = false): void
    {
        $pdf = $this->files()->where('kind', 'manuscript')->orderByDesc('version')->firstOrFail();
        $path = 'camera-ready/'.Str::random(40).'.pdf';
        Storage::copy($pdf->path, $path);

        $old = $this->camera_ready_path;
        $from = $this->status;
        $this->forceFill([
            'camera_ready_path' => $path,
            'camera_ready_name' => $pdf->name,
            'camera_ready_size' => $pdf->size,
            'camera_ready_at' => now(),
            'camera_ready_approved_at' => now(),
        ]);
        $this->status = SubmissionStatus::CameraReady;
        $this->save();
        $this->log(SubmissionAction::CameraReadyApproved, $by, $from !== $this->status ? $from : null, null, array_filter([
            'from_latest' => true, 'version' => $pdf->version, 'with_decision' => $withDecision ?: null,
        ]));
        if ($old) {
            Storage::delete($old);
        }
    }

    /** Version number of the current manuscript (the latest PDF). */
    public function currentVersion(): int
    {
        return max(1, (int) $this->files()->where('kind', 'manuscript')->max('version'));
    }

    /**
     * Files that come with a manuscript version: ['word' => file, 'turnitin' => file, 'supplementary' => [files]].
     * A Turnitin report left out of a revision keeps the earlier one as the current report.
     */
    /**
     * A file the editor sends the author with a screening or post-review decision (2026-11-12): an annotated PDF, a
     * template, ... Kept as kind 'editor' with the version and round it answers; the event's meta points to it.
     */
    public function addEditorFile(?UploadedFile $file, string $stage, User $by): ?array
    {
        if (! $file) {
            return null;
        }
        $stored = $this->files()->create([
            'version' => $this->currentVersion(), 'kind' => 'editor', 'stage' => $stage, 'round' => (int) $this->review_round,
            'path' => $file->store('submissions/editor'), 'name' => $file->getClientOriginalName(), 'size' => $file->getSize(),
            'user_id' => $by->id,
        ]);

        return ['id' => $stored->id, 'name' => $stored->name, 'url' => $stored->toClient()['url']];
    }

    public function addExtraFiles(array $files, int $version, string $stage, ?User $by): void
    {
        foreach ($files as $kind => $list) {
            foreach (array_filter(is_array($list) ? $list : [$list]) as $file) {
                $this->files()->create([
                    'version' => $version, 'kind' => $kind, 'stage' => $stage, 'round' => (int) $this->review_round,
                    'path' => $file->store("submissions/{$kind}"), 'name' => $file->getClientOriginalName(), 'size' => $file->getSize(),
                    'user_id' => $by?->id,
                ]);
            }
        }
    }

    /**
     * The paper's current files: the latest PDF, Word file and Turnitin report, and every supplementary file.
     * For the author, editors, admin and the journal (reviewers only get the PDF, through toClient()).
     */
    public function currentFiles(): array
    {
        $files = $this->files()->whereIn('kind', ['manuscript', 'word', 'turnitin', 'supplementary'])->orderBy('id')->get();
        $latest = fn (string $kind) => $files->where('kind', $kind)->last()?->toClient();

        return [
            'pdf' => $latest('manuscript'),
            'word' => $latest('word'),
            'turnitin' => $latest('turnitin'),
            'supplementary' => $files->where('kind', 'supplementary')->map->toClient()->values()->all(),
        ];
    }

    /** Reviews the author must answer when revising after peer review (the decided round), numbered like the author sees them. */
    public function reviewsToAnswer(): array
    {
        if (! in_array($this->status, [SubmissionStatus::ReviewRevision], true)) {
            return [];
        }

        // Numbered like the author sees them; a reviewer who left no comment and no file has nothing to answer
        return $this->reviews()->whereNotNull('submitted_at')->where('round', $this->review_round)->orderBy('id')->get()
            ->values()->map(fn (Review $review, int $i) => [
                'id' => $review->id,
                'number' => $i + 1,
                'recommendation' => $review->recommendation->value,
                'comment' => $review->comment,
                'file' => $review->fileForAuthor($i + 1),
            ])
            ->filter(fn (array $review) => trim((string) $review['comment']) !== '' || $review['file'])
            ->values()->all();
    }

    /**
     * Manuscript versions, newest first, with the response letter and the answers that came with each.
     * $viewer: staff (sees reviewer names) | author | reviewer (reviewers are numbered, never named).
     */
    public function versionsFor(string $viewer): array
    {
        $files = $this->files()->orderBy('version')->orderBy('id')->get()->groupBy('version');
        $responses = $this->responses()->with('review.reviewer')->orderBy('id')->get()->groupBy('version');
        // Reviewer N within its round, keyed by review id (the same numbers the author sees)
        $numbers = [];
        foreach ($this->reviews()->whereNotNull('submitted_at')->orderBy('round')->orderBy('id')->get()->groupBy('round') as $reviews) {
            foreach ($reviews->values() as $i => $review) {
                $numbers[$review->id] = $i + 1;
            }
        }

        return $files->map(function ($group, $version) use ($responses, $numbers, $viewer) {
            $manuscript = $group->firstWhere('kind', 'manuscript');
            $letter = $group->firstWhere('kind', 'response');
            // Word, Turnitin and supplementary files: not for reviewers (they work from the PDF)
            $extra = fn (string $kind) => $viewer === 'reviewer' ? [] : $group->where('kind', $kind)->map->toClient()->values()->all();

            return [
                'version' => $version,
                'stage' => $manuscript?->stage,
                'round' => $manuscript?->round,
                'at' => $manuscript?->created_at->toIso8601String(),
                'file' => $manuscript?->toClient(),
                'letter' => $letter?->toClient(),
                'word' => $extra('word'),
                'turnitin' => $extra('turnitin'),
                'supplementary' => $extra('supplementary'),
                'responses' => ($responses[$version] ?? collect())->map(fn (RevisionResponse $response) => [
                    'to' => $response->review_id ? 'reviewer' : 'editor',
                    'number' => $response->review_id ? ($numbers[$response->review_id] ?? null) : null,
                    'reviewer' => $viewer === 'staff' ? $response->review?->reviewer?->name : null,
                    'comment' => $response->review?->comment,
                    // The reviewer's notes file next to the answer, to check the revision against it
                    'file' => match (true) {
                        ! $response->review?->attachment_path => null,
                        $viewer === 'staff' => ['name' => $response->review->attachment_name, 'url' => "/admin/review-files/{$response->review->id}"],
                        $viewer === 'author' => $response->review->fileForAuthor($numbers[$response->review_id] ?? 1),
                        default => null,
                    },
                    'body' => $response->body,
                ])->values(),
            ];
        })->filter(fn ($v) => $v['file'])->sortKeysDesc()->values()->all();
    }

    /** Editor work queues (Submissions tabs, editor home), in tab order. */
    public const QUEUES = ['screening', 'needReviewers', 'readyToDecide', 'revised', 'overdue', 'unavailable'];

    /**
     * Papers waiting for an editor, by kind of work:
     *   screening      waiting for screening
     *   needReviewers  in review with nobody assigned this round
     *   readyToDecide  in review and every reviewer of this round has sent their evaluation
     *   revised        the author sent a revision after review (a revision after screening waits in "screening",
     *                  marked "revision N" by RevisionBadge)
     *   overdue        a reviewer of this round is past the due date
     *   unavailable    a reviewer of this round said they cannot review (replace them)
     */
    public function scopeQueue(Builder $query, string $queue): void
    {
        $round = fn ($review) => $review->whereColumn('reviews.round', 'submissions.review_round');
        $open = fn ($review) => $round($review)->whereNull('submitted_at');

        match ($queue) {
            'screening' => $query->where('status', SubmissionStatus::Screening),
            'needReviewers' => $query->where('status', SubmissionStatus::InReview)->whereDoesntHave('reviews', $round),
            'readyToDecide' => $query->where('status', SubmissionStatus::InReview)->whereHas('reviews', $round)->whereDoesntHave('reviews', $open),
            'revised' => $query->where('status', SubmissionStatus::Revised),
            'overdue' => $query->where('status', SubmissionStatus::InReview)
                ->whereHas('reviews', fn ($review) => $open($review)->whereDate('due_date', '<', today())),
            'unavailable' => $query->where('status', SubmissionStatus::InReview)
                ->whereHas('reviews', fn ($review) => $open($review)->whereNotNull('unavailable_at')),
            default => null,
        };
    }

    /**
     * Tabs of Submissions (2026-11-12): where each conference-track paper stands, one tab per paper. A paper sent back
     * for revision stays in the tab it was sent back from (its status column says the author is revising) and comes
     * back to the matching "revised" tab:
     *   screening         new papers to screen, and those sent back at screening (author revising)
     *   revisedScreening  revisions sent back after screening, to screen again (and if sent back again)
     *   findReviewers     passed screening: nobody assigned this round, a reviewer late, or one who cannot review
     *   reviewing         reviewers at work
     *   decide            every review in, or a revision sent back after review: accept, revise, reject or review again
     *                     (sent back after review stays here while the author revises)
     *   awaitingFinal     accepted: waiting for the camera-ready file, or received and waiting for the editors' check
     *   cameraReady       camera-ready checked (approved): nothing left to do
     * Rejected papers are only under "all".
     */
    public const STAGES = ['screening', 'revisedScreening', 'findReviewers', 'reviewing', 'decide', 'awaitingFinal', 'cameraReady'];

    /** Old work-queue links (?queue=) open the matching tab. */
    public const STAGE_OF_QUEUE = ['screening' => 'screening', 'needReviewers' => 'findReviewers', 'overdue' => 'findReviewers', 'unavailable' => 'findReviewers', 'readyToDecide' => 'decide', 'revised' => 'decide'];

    public function scopeStage(Builder $query, string $stage): void
    {
        // Revisions the author sent back from screening / from review (the event keeps the status it left)
        $back = fn (SubmissionStatus $from) => fn ($event) => $event->where('action', SubmissionAction::Resubmitted)->where('from_status', $from->value);
        $round = fn ($review) => $review->whereColumn('reviews.round', 'submissions.review_round');
        $open = fn ($review) => $round($review)->whereNull('submitted_at');
        $needsReviewers = fn ($q) => $q->whereDoesntHave('reviews', $round)
            ->orWhereHas('reviews', fn ($review) => $open($review)->where(fn ($late) => $late->whereDate('due_date', '<', today())->orWhereNotNull('unavailable_at')));
        $screeningSide = [SubmissionStatus::Screening, SubmissionStatus::Revision];

        match ($stage) {
            'screening' => $query->whereIn('status', $screeningSide)->whereDoesntHave('events', $back(SubmissionStatus::Revision)),
            'revisedScreening' => $query->whereIn('status', $screeningSide)->whereHas('events', $back(SubmissionStatus::Revision)),
            'findReviewers' => $query->where('status', SubmissionStatus::InReview)->where($needsReviewers),
            // Reviewers still at work (nobody missing, late or unavailable)
            'reviewing' => $query->where('status', SubmissionStatus::InReview)->whereNot($needsReviewers)->whereHas('reviews', $open),
            // Waiting for the editors' decision: every review of the round in, or a revision sent back after review;
            // sent back for revision after review stays here while the author revises
            'decide' => $query->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('status', SubmissionStatus::InReview)->whereHas('reviews', $round)->whereDoesntHave('reviews', $open))
                ->orWhereIn('status', [SubmissionStatus::Revised, SubmissionStatus::ReviewRevision])),
            // Accepted: waiting for the camera-ready file (or its fixes), or received and waiting for the editors' check
            'awaitingFinal' => $query->where(fn ($q) => $q->where('status', SubmissionStatus::Accepted)
                ->orWhere(fn ($q) => $q->where('status', SubmissionStatus::CameraReady)->whereNull('camera_ready_approved_at'))),
            // Camera-ready checked: nothing left to do here
            'cameraReady' => $query->where('status', SubmissionStatus::CameraReady)->whereNotNull('camera_ready_approved_at'),
            default => null,
        };
    }

    /**
     * Each tab's number: the papers waiting for the editors ('count', gold), and every paper in the tab ('total').
     * Authors revising and reviewers at work are not the editors' move; camera-ready waits for the author.
     */
    public static function stageCounts(): array
    {
        $in = fn (string $stage) => static::query()->currentYear()->stage($stage);
        $editorsMove = [
            'screening' => fn () => $in('screening')->where('status', SubmissionStatus::Screening)->count(),
            'revisedScreening' => fn () => $in('revisedScreening')->where('status', SubmissionStatus::Screening)->count(),
            'findReviewers' => fn () => $in('findReviewers')->count(),
            'reviewing' => fn () => 0, // the reviewers' move
            'decide' => fn () => $in('decide')->where('status', '!=', SubmissionStatus::ReviewRevision)->count(),
            'awaitingFinal' => fn () => $in('awaitingFinal')->where('status', SubmissionStatus::CameraReady)->count(),
            'cameraReady' => fn () => 0,
        ];

        return collect(self::STAGES)->mapWithKeys(fn (string $stage) => [$stage => ['count' => $editorsMove[$stage](), 'total' => $in($stage)->count()]])->all();
    }

    /**
     * First-gate tabs of Selection (2026-11-11), in order: still waiting, waiting past the first-gate deadline,
     * offered to a journal (the author has not answered), gone to a journal (the author accepted), conference track, all.
     */
    public const GATE_TABS = ['waiting', 'late', 'offered', 'journal', 'conference', 'all'];

    public function scopeGate(Builder $query, string $tab): void
    {
        $late = (bool) static::journalDeadline()?->isPast();

        match ($tab) {
            'waiting' => $query->where('status', SubmissionStatus::Submitted)->when($late, fn ($none) => $none->whereRaw('1 = 0')),
            'late' => $query->where('status', SubmissionStatus::Submitted)->when(! $late, fn ($none) => $none->whereRaw('1 = 0')),
            'offered' => $query->where('status', SubmissionStatus::JournalSelected),
            'journal' => $query->where('status', SubmissionStatus::JournalAccepted),
            'conference' => $query->whereIn('status', SubmissionStatus::conferenceTrack()),
            default => null,
        };
    }

    /** Where the paper stands at the first gate: journal (offered or accepted) | conference | null while it waits. */
    public function gate(): ?string
    {
        return match ($this->status) {
            SubmissionStatus::Submitted => null,
            SubmissionStatus::JournalSelected, SubmissionStatus::JournalAccepted => 'journal',
            default => 'conference',
        };
    }

    /** `declined_journal`: the author declined a journal offer, or let it lapse (shown on the conference rows). */
    public function scopeWithDeclinedJournal(Builder $query): void
    {
        $query->withExists(['events as declined_journal' => fn ($event) => $event
            ->where('action', SubmissionAction::JournalOfferAnswered)->where('to_status', SubmissionStatus::Screening->value)]);
    }

    /**
     * Whose move it is now, and what they have to do: { who: editor|reviewer|author|none, task, done?, total?, late? }.
     * Needs reviews_total / reviews_done / reviews_late counts for papers in review (SubmissionController::index).
     */
    public function waitingOn(): array
    {
        $scheduled = (bool) $this->presentation_at;

        return match ($this->status) {
            // First gate (2026-11-11): after the deadline an editor offers it to a journal or sends it to the conference
            SubmissionStatus::Submitted => static::isClosed()
                ? ['who' => 'editor', 'task' => 'firstGate', 'late' => $this->isPastJournalDeadline()]
                : ['who' => 'editor', 'task' => 'journalAfterDeadline'],
            SubmissionStatus::JournalSelected => ['who' => 'author', 'task' => 'answerJournal'],
            SubmissionStatus::Screening => ['who' => 'editor', 'task' => 'screen'],
            SubmissionStatus::InReview => match (true) {
                ! $this->reviews_total => ['who' => 'editor', 'task' => 'assignReviewers'],
                $this->reviews_unavailable > 0 => ['who' => 'editor', 'task' => 'replaceReviewer'],
                $this->reviews_done < $this->reviews_total => ['who' => 'reviewer', 'task' => 'review', 'done' => $this->reviews_done, 'total' => $this->reviews_total, 'late' => $this->reviews_late],
                default => ['who' => 'editor', 'task' => 'decide'],
            },
            SubmissionStatus::Revision, SubmissionStatus::ReviewRevision => ['who' => 'author', 'task' => 'revise'],
            SubmissionStatus::Revised => ['who' => 'editor', 'task' => 'decideRevision'],
            SubmissionStatus::Accepted => ['who' => 'author', 'task' => 'cameraReady'],
            SubmissionStatus::CameraReady => match (true) {
                ! $this->camera_ready_approved_at => ['who' => 'editor', 'task' => 'checkCameraReady'],
                ! $scheduled => ['who' => 'editor', 'task' => 'schedule'],
                ! $this->slides_path => ['who' => 'author', 'task' => 'slides'],
                default => ['who' => 'none', 'task' => 'done'],
            },
            SubmissionStatus::JournalAccepted => match (true) {
                ! $scheduled => ['who' => 'editor', 'task' => 'schedule'],
                ! $this->slides_path => ['who' => 'author', 'task' => 'slides'],
                default => ['who' => 'none', 'task' => 'done'],
            },
            SubmissionStatus::Rejected, SubmissionStatus::ReviewRejected => ['who' => 'none', 'task' => 'closed'],
        };
    }

    /** `had_revision`: the author has sent a revision before (the author's journey keeps the revision step). */
    /**
     * Papers that can be given a presentation slot (2026-11-12): camera-ready approved by the editors,
     * or gone to a journal (they still present at the conference).
     */
    public function canBeScheduled(): bool
    {
        return ($this->status === SubmissionStatus::CameraReady && $this->camera_ready_approved_at) || $this->status === SubmissionStatus::JournalAccepted;
    }

    public function scopeSchedulable(Builder $query): void
    {
        $query->where(fn ($q) => $q->where(fn ($q) => $q->where('status', SubmissionStatus::CameraReady)->whereNotNull('camera_ready_approved_at'))
            ->orWhere('status', SubmissionStatus::JournalAccepted));
    }

    public function presentationSession(): BelongsTo
    {
        return $this->belongsTo(PresentationSession::class);
    }

    /** How many revisions the author has sent back (revision_count), for the "revision N" badge. */
    public function scopeWithRevisionCount(Builder $query): void
    {
        $query->withCount(['events as revision_count' => fn ($event) => $event->where('action', SubmissionAction::Resubmitted)]);
    }

    public function scopeWithHadRevision(Builder $query): void
    {
        $query->withExists(['events as had_revision' => fn ($event) => $event->where('action', SubmissionAction::Resubmitted)]);
    }

    /** This round's review counts that waitingOn() needs: assigned, sent, late, and reviewers who cannot review. */
    public function scopeWithReviewCounts(Builder $query): void
    {
        $round = fn ($review) => $review->whereColumn('reviews.round', 'submissions.review_round');

        $query->withCount([
            'reviews as reviews_total' => $round,
            'reviews as reviews_done' => fn ($review) => $round($review)->whereNotNull('submitted_at'),
            'reviews as reviews_late' => fn ($review) => $round($review)->whereNull('submitted_at')->whereDate('due_date', '<', today()),
            'reviews as reviews_unavailable' => fn ($review) => $round($review)->whereNull('submitted_at')->whereNotNull('unavailable_at'),
        ]);
    }

    /**
     * The next step as the author sees it: their own task (with the revision due date), or a plain
     * "with the editors" / "in review" (no reviewer counts or editor tasks are shown to authors).
     */
    public function nextForAuthor(): array
    {
        $waiting = $this->waitingOn();

        if ($waiting['who'] === 'author') {
            $due = match ($waiting['task']) {
                'revise' => $this->revision_due_date,
                'slides' => $this->slidesUntil(),
                'answerJournal' => $this->journal_answer_due,
                default => null,
            };

            return ['mine' => true, 'task' => $waiting['task'], 'due' => $due?->toDateString(), 'daysLate' => $due?->endOfDay()->isPast() ? (int) $due->diffInDays(today()) : 0];
        }

        return ['mine' => false, 'task' => match (true) {
            $waiting['who'] === 'none' => $waiting['task'], // done | closed
            // Accepted a journal offer: the author works with the journal; the system calls them back for the slides
            $this->status === SubmissionStatus::JournalAccepted => 'atJournal',
            $waiting['task'] === 'journalAfterDeadline' => 'editableUntilDeadline',
            $waiting['who'] === 'journal' => 'withJournal',
            $waiting['who'] === 'reviewer' || in_array($waiting['task'], ['assignReviewers', 'replaceReviewer'], true) => 'inReview',
            default => 'withEditor',
        }];
    }

    /** Number of papers in each queue: { screening: 3, ... } */
    public static function queueCounts(): array
    {
        return collect(self::QUEUES)->mapWithKeys(fn ($queue) => [$queue => static::query()->currentYear()->queue($queue)->count()])->all();
    }

    /** Reviews of the current peer-review round, oldest assignment first. */
    public function currentReviews(): HasMany
    {
        return $this->reviews()->where('round', $this->review_round)->orderBy('id');
    }

    /** Reviewers can be assigned while the paper waits for peer review. */
    public function canAssignReviewers(): bool
    {
        return $this->status === SubmissionStatus::InReview;
    }

    /** History, newest first. */
    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class)->latest('id');
    }

    /** Add a history entry; status changes are recorded with their before/after values. */
    public function log(SubmissionAction $action, ?User $user, ?SubmissionStatus $from = null, ?string $comment = null, ?array $meta = null): void
    {
        $this->events()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $from ? $this->status->value : null,
            'comment' => $comment,
            'meta' => $meta,
        ]);
    }

    /**
     * Before the deadline the author may edit a paper awaiting screening.
     * A paper sent back for revision (screening or review) can be edited at any time, even after
     * the submission deadline or the revision due date (late revisions are flagged, not blocked).
     */
    public function canBeEditedByAuthor(): bool
    {
        return $this->status->awaitsAuthorRevision()
            || (static::isOpen() && $this->status === SubmissionStatus::Submitted);
    }

    /**
     * The editor's decision after review: once at least one reviewer of the round has sent an evaluation,
     * or directly on a revised paper (without another review round).
     */
    public function canBeDecided(): bool
    {
        return $this->status === SubmissionStatus::Revised
            || ($this->status === SubmissionStatus::InReview && $this->currentReviews()->whereNotNull('submitted_at')->exists());
    }

    /** A revised paper can go back to reviewers for a new round instead of a direct decision. */
    public function canStartReviewRound(): bool
    {
        return $this->status === SubmissionStatus::Revised;
    }

    /**
     * Reviewer comments the author may read: submitted reviews of rounds the editor has already decided,
     * numbered per round ("Reviewer 1", "Reviewer 2", ...) with no names.
     */
    public function reviewsForAuthor(): array
    {
        $lastDecidedRound = $this->status === SubmissionStatus::InReview ? $this->review_round - 1 : $this->review_round;

        return $this->reviews()
            ->whereNotNull('submitted_at')
            ->where('round', '<=', $lastDecidedRound)
            ->orderBy('round')->orderBy('id')
            ->get()
            ->groupBy('round')
            ->map(fn ($reviews, $round) => [
                'round' => $round,
                'reviews' => $reviews->values()->map(fn (Review $review, int $i) => [
                    'id' => $review->id,
                    'number' => $i + 1,
                    'recommendation' => $review->recommendation->value,
                    'comment' => $review->comment,
                    'file' => $review->fileForAuthor($i + 1),
                ]),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /** Editor screening: papers the journal did not take (always after the deadline), one decision per round. */
    public function canBeScreened(): bool
    {
        return $this->status === SubmissionStatus::Screening;
    }

    /** The journal's decision deadline set by admin (a warning only; nothing moves by itself). */
    public static function journalDeadline(): ?Carbon
    {
        $value = Setting::get('journal_deadline');

        return $value ? Carbon::parse($value)->endOfDay() : null;
    }

    /** The journal can see this paper (papers in the conference track are hidden from it). */
    public function isJournalStage(): bool
    {
        return in_array($this->status, SubmissionStatus::journalStage(), true);
    }

    public function scopeJournalStage(Builder $query): void
    {
        $query->whereIn('status', SubmissionStatus::journalStage());
    }

    /**
     * The first gate is open for this paper: after the submission deadline (the files are final), while it waits or
     * while its journal offer is unanswered (the offer may be moved to another journal, or to the conference).
     */
    public function isAtFirstGate(): bool
    {
        return static::isClosed() && in_array($this->status, [SubmissionStatus::Submitted, SubmissionStatus::JournalSelected], true);
    }

    /**
     * Put the paper in a track; a change is logged (the first placement too, "from" then empty).
     * Authors never pick one (2026-11-12): editors set it on Selection, at the first-gate decision or on the paper page.
     */
    public function setTrack(User $by, int $trackId): bool
    {
        if ($trackId === $this->track_id) {
            return false;
        }
        $fromTrack = $this->track;
        $this->track_id = $trackId;
        $this->save();
        $this->log(SubmissionAction::TrackChanged, $by, null, null, ['from' => $fromTrack?->toClient()['name'], 'to' => $this->load('track')->track->toClient()['name']]);

        return true;
    }

    /** Editors and admin set the track on Selection while the paper is at the first gate, also before submissions close. */
    public function trackCanBeSetBy(User $user): bool
    {
        return in_array($this->status, [SubmissionStatus::Submitted, SubmissionStatus::JournalSelected], true)
            && $user->moduleAccess('submissions') === 'full';
    }

    /** Admin, editors and the journal role decide the first gate alike (client request 2026-11-11). */
    public function canBeRoutedBy(User $user): bool
    {
        return $this->isAtFirstGate() && $user->moduleAccess('selection') === 'full';
    }

    /** Still waiting for the first gate although its deadline (Settings, a warning only) has passed. */
    public function isPastJournalDeadline(): bool
    {
        return $this->status === SubmissionStatus::Submitted && (bool) static::journalDeadline()?->isPast();
    }

    /** Days the author has to answer a journal offer (Settings). */
    public static function journalAnswerDays(): int
    {
        return max(1, (int) Setting::get('journal_answer_days'));
    }

    /**
     * First gate: offer the paper to a journal (the author answers by the due date), or send it to the conference
     * track. The track is set at the same time; a changed track is logged as its own entry.
     */
    public function route(User $by, ?int $trackId, ?Journal $journal, ?string $comment = null): void
    {
        if ($trackId) {
            $this->setTrack($by, $trackId);
        }

        $from = $this->status;
        if ($journal) {
            $this->forceFill([
                'status' => SubmissionStatus::JournalSelected,
                'journal_id' => $journal->id,
                'journal_answer_due' => today()->addDays(static::journalAnswerDays()),
                'journal_reminded_at' => null,
            ])->save();
            $this->log(SubmissionAction::JournalScreened, $by, $from, $comment, [
                'journal_id' => $journal->id, 'journal' => $journal->name, 'due' => $this->journal_answer_due->toDateString(),
            ]);

            return;
        }

        // A withdrawn offer keeps no journal; an answered one keeps it for the record
        $this->forceFill(['status' => SubmissionStatus::Screening, 'journal_id' => null, 'journal_answer_due' => null])->save();
        $this->log(SubmissionAction::JournalScreened, $by, $from, $comment);
    }

    /** The author's answer to the journal offer: accept (goes to the journal), decline or expired (conference track). */
    public function answerJournalOffer(string $answer, ?User $by): void
    {
        $from = $this->status;
        $this->forceFill(['status' => $answer === 'accept' ? SubmissionStatus::JournalAccepted : SubmissionStatus::Screening])->save();
        $this->log(SubmissionAction::JournalOfferAnswered, $by, $from, null, ['answer' => $answer, 'journal' => $this->journal?->name]);
    }

    /** Offers still waiting for the author's answer this year (the Selection reminder button). */
    public static function pendingOffers()
    {
        return static::currentYear()->where('status', SubmissionStatus::JournalSelected)->with('journal')->get();
    }

    /** "Remind the authors who have not answered" (an editor's button, 2026-11-13): one email each, logged with who sent it. */
    public static function remindJournalOffers(?User $by): int
    {
        $pending = static::pendingOffers();
        $pending->each(function (Submission $submission) use ($by) {
            $submission->forceFill(['journal_reminded_at' => now(), 'author_reminded_at' => now(), 'author_reminded_by' => $by?->id])->save();
            $submission->log(SubmissionAction::JournalOfferReminded, $by, null, null, ['journal' => $submission->journal?->name, 'due' => $submission->journal_answer_due?->toDateString()]);
        });

        return $pending->count();
    }

    /**
     * What the author is being waited for, if a reminder makes sense (2026-11-13): the journal offer, a revision
     * (screening or after review) or the camera-ready file. Null otherwise.
     */
    public function authorReminderTask(): ?string
    {
        return match ($this->status) {
            SubmissionStatus::JournalSelected => 'journal',
            SubmissionStatus::Revision, SubmissionStatus::ReviewRevision => 'revision',
            SubmissionStatus::Accepted => 'cameraReady',
            default => null,
        };
    }

    /** May this person remind the author? Editors (and admin); for a journal offer also the journal role (Selection). */
    public function authorCanBeRemindedBy(User $user): bool
    {
        $task = $this->authorReminderTask();

        return $task && ($user->moduleAccess('submissions') === 'full' || ($task === 'journal' && $user->moduleAccess('selection') === 'full'));
    }

    /** The history entry a reminder writes: [action, meta] (the journal offer keeps its own action and template). */
    private function reminderEntry(): array
    {
        $task = $this->authorReminderTask();

        return $task === 'journal'
            ? [SubmissionAction::JournalOfferReminded, ['journal' => $this->journal?->name, 'due' => $this->journal_answer_due?->toDateString()]]
            : [SubmissionAction::AuthorReminded, array_filter(['task' => $task, 'due' => $task === 'revision' ? $this->revision_due_date?->toDateString() : null])];
    }

    /** One reminder email to this paper's author, logged with who sent it (WorkflowMail picks the template). */
    public function remindAuthor(User $by): void
    {
        [$action, $meta] = $this->reminderEntry();
        $this->forceFill(['author_reminded_at' => now(), 'author_reminded_by' => $by->id]);
        if ($action === SubmissionAction::JournalOfferReminded) {
            $this->journal_reminded_at = now();
        }
        $this->save();
        $this->log($action, $by, null, null, $meta);
    }

    /** The reminder exactly as the author would get it, without sending it (shown before the editor confirms). */
    public function reminderPreview(): ?array
    {
        [$action, $meta] = $this->reminderEntry();

        return $this->previewMail($action, $meta);
    }

    /**
     * Any step's email exactly as it would go out, without sending it (2026-11-13): the history entry is built but not
     * saved, and WorkflowMail::compose() fills the same template the real send uses.
     */
    public function previewMail(SubmissionAction $action, ?array $meta = null, ?string $comment = null, ?SubmissionStatus $toStatus = null): ?array
    {
        $event = new SubmissionEvent(['action' => $action, 'meta' => $meta, 'comment' => $comment, 'to_status' => $toStatus?->value]);
        $event->setRelation('submission', $this);
        $mail = WorkflowMail::compose($event);

        return $mail ? [
            'to' => ['name' => $mail['to']->name, 'email' => $mail['to']->email],
            'subject' => SiteContent::all()['conference']['shortName'].' – '.$mail['subject'], // as TemplatedMail sends it
            'bodyTh' => $mail['body_th'],
            'bodyEn' => $mail['body_en'],
            'template' => $mail['key'], // links to Announcements › Email templates
        ] : null;
    }

    /** "Close" a revision the author never sent (2026-11-13): editors only, while the paper waits for a revision. */
    public function canCloseRevision(): bool
    {
        return $this->status->awaitsAuthorRevision();
    }

    /** The status a closed revision ends in: not accepted at screening, or not accepted after review. */
    public function closedRevisionStatus(): SubmissionStatus
    {
        return $this->status === SubmissionStatus::Revision ? SubmissionStatus::Rejected : SubmissionStatus::ReviewRejected;
    }

    /** Close it with a reason: the paper is not accepted and the author is told (email revision_closed). */
    public function closeRevision(User $by, string $reason): void
    {
        $from = $this->status;
        $this->status = $this->closedRevisionStatus();
        $this->revision_due_date = null;
        $this->save();
        $this->log(SubmissionAction::RevisionClosed, $by, $from, $reason);
    }

    /** The answer date has passed and the author has not answered: shown in red for the editors to act on. */
    public function journalOfferOverdue(): bool
    {
        return $this->status === SubmissionStatus::JournalSelected && $this->journal_answer_due && $this->journal_answer_due->lt(today());
    }

    /** Last moment the presenter may upload or replace slides: the talk, or a few days before it (Settings). */
    public function slidesUntil(): ?Carbon
    {
        if (! $this->presentation_at) {
            return null;
        }
        $days = Setting::get('slides_cutoff_days');

        return $days ? $this->presentation_at->copy()->subDays((int) $days)->endOfDay() : $this->presentation_at->copy();
    }

    /** Slides open once the paper presents and has a slot, until slidesUntil(). */
    public function canUploadSlides(): bool
    {
        return $this->status->presents() && $this->presentation_at && $this->slidesUntil()->isFuture();
    }

    /**
     * Authors whose name or email matches the given user: a possible conflict of interest
     * for an editor or reviewer. The editor decides what to do; nothing is blocked.
     */
    public function conflictsWith(User $user): bool
    {
        $name = Str::squish(Str::lower($user->name));
        $email = Str::lower($user->email);

        return $this->user_id === $user->id || $this->authors->contains(
            fn (SubmissionAuthor $author) => Str::squish(Str::lower($author->name)) === $name
                || ($author->email && Str::lower($author->email) === $email)
        );
    }

    /** Shape shared by the list and detail pages. */
    public function toClient(bool $full = false): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'title' => ['en' => $this->title_en, 'th' => $this->title_th],
            'track' => $this->track?->toClient(),
            'status' => $this->status->value,
            'reviewRound' => $this->review_round,
            'revisionDueDate' => $this->revision_due_date?->toDateString(),
            // Camera-ready check (2026-11-12): approved, or sent back for fixes (accepted with a file already sent)
            'cameraReadyApproved' => $this->camera_ready_approved_at?->toIso8601String(),
            // Reminding the author from the row (2026-11-13): what they are waited for, and the last reminder
            'remindTask' => $this->authorReminderTask(),
            'canCloseRevision' => $this->canCloseRevision(),
            'authorRemindedAt' => $this->author_reminded_at?->toIso8601String(),
            'cameraReadyFix' => $this->status === SubmissionStatus::Accepted && $this->camera_ready_path !== null,
            'submittedAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'authors' => $this->authors->map->only('name', 'affiliation', 'email')->values(),
            // First gate: the journal offered (name, link, terms) and the last day to answer
            'journal' => $this->journal?->toClient(),
            'journalAnswerDue' => $this->journal_answer_due?->toDateString(),
            'journalOfferOverdue' => $this->journalOfferOverdue(),
            'journalRemindedAt' => $this->journal_reminded_at?->toIso8601String(),
        ];

        if ($full) {
            $data += [
                'abstract' => ['en' => $this->abstract_en, 'th' => $this->abstract_th],
                'keywords' => ['en' => $this->keywords_en, 'th' => $this->keywords_th],
                'file' => ['name' => $this->file_name, 'size' => $this->file_size, 'url' => "/admin/submissions/{$this->id}/file"],
                'submitter' => $this->user?->only('id', 'name', 'email'),
                'publication' => [
                    'cameraReady' => $this->camera_ready_path ? [
                        'name' => $this->camera_ready_name,
                        'size' => $this->camera_ready_size,
                        'at' => $this->camera_ready_at->toIso8601String(),
                        'url' => "/admin/submissions/{$this->id}/camera-ready",
                    ] : null,
                    'presentation' => $this->presentation_at ? [
                        'at' => $this->presentation_at->toIso8601String(),
                        'room' => $this->presentation_room,
                        'session' => $this->presentation_session_id,
                    ] : null,
                    'slides' => $this->slides_path ? [
                        'name' => $this->slides_name,
                        'size' => $this->slides_size,
                        'at' => $this->slides_at->toIso8601String(),
                        'url' => "/admin/submissions/{$this->id}/slides",
                    ] : null,
                    'slidesUntil' => $this->slidesUntil()?->toIso8601String(),
                    'canUploadSlides' => $this->canUploadSlides(),
                ],
            ];
        }

        return $data;
    }
}
