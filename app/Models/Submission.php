<?php

namespace App\Models;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
            'presentation_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Submission $submission) {
            $submission->forceFill(['code' => sprintf('SKDIIT27-%04d', $submission->id)])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
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

    /** The deadline has passed: every paper is locked and screening can start. */
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
        $version = (int) $this->files()->where('kind', 'manuscript')->max('version') + 1;
        $this->files()->create([
            'version' => $version, 'kind' => 'manuscript', 'stage' => $stage, 'round' => $this->review_round,
            'path' => $stored['file_path'], 'name' => $stored['file_name'], 'size' => $stored['file_size'], 'user_id' => $by?->id,
        ]);

        return $version;
    }

    /** Reviews the author must answer when revising after peer review (the decided round), numbered like the author sees them. */
    public function reviewsToAnswer(): array
    {
        if (! in_array($this->status, [SubmissionStatus::RevisionMinor, SubmissionStatus::RevisionMajor], true)) {
            return [];
        }

        return $this->reviews()->whereNotNull('submitted_at')->where('round', $this->review_round)->orderBy('id')->get()
            ->values()->map(fn (Review $review, int $i) => [
                'id' => $review->id,
                'number' => $i + 1,
                'recommendation' => $review->recommendation->value,
                'comment' => $review->comment,
            ])->all();
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

            return [
                'version' => $version,
                'stage' => $manuscript?->stage,
                'round' => $manuscript?->round,
                'at' => $manuscript?->created_at->toIso8601String(),
                'file' => $manuscript?->toClient(),
                'letter' => $letter?->toClient(),
                'responses' => ($responses[$version] ?? collect())->map(fn (RevisionResponse $response) => [
                    'to' => $response->review_id ? 'reviewer' : 'editor',
                    'number' => $response->review_id ? ($numbers[$response->review_id] ?? null) : null,
                    'reviewer' => $viewer === 'staff' ? $response->review?->reviewer?->name : null,
                    'comment' => $response->review?->comment,
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
     *   revised        the author sent a revision
     *   overdue        a reviewer of this round is past the due date
     *   unavailable    a reviewer of this round said they cannot review (replace them)
     */
    public function scopeQueue(Builder $query, string $queue): void
    {
        $round = fn ($review) => $review->whereColumn('reviews.round', 'submissions.review_round');
        $open = fn ($review) => $round($review)->whereNull('submitted_at');

        match ($queue) {
            'screening' => $query->where('status', SubmissionStatus::Submitted),
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
     * Whose move it is now, and what they have to do: { who: editor|reviewer|author|none, task, done?, total?, late? }.
     * Needs reviews_total / reviews_done / reviews_late counts for papers in review (SubmissionController::index).
     */
    public function waitingOn(): array
    {
        $scheduled = (bool) $this->presentation_at;

        return match ($this->status) {
            SubmissionStatus::Submitted => ['who' => 'editor', 'task' => static::isClosed() ? 'screen' : 'screenAfterDeadline'],
            SubmissionStatus::InReview => match (true) {
                ! $this->reviews_total => ['who' => 'editor', 'task' => 'assignReviewers'],
                $this->reviews_unavailable > 0 => ['who' => 'editor', 'task' => 'replaceReviewer'],
                $this->reviews_done < $this->reviews_total => ['who' => 'reviewer', 'task' => 'review', 'done' => $this->reviews_done, 'total' => $this->reviews_total, 'late' => $this->reviews_late],
                default => ['who' => 'editor', 'task' => 'decide'],
            },
            SubmissionStatus::Revision, SubmissionStatus::RevisionMinor, SubmissionStatus::RevisionMajor => ['who' => 'author', 'task' => 'revise'],
            SubmissionStatus::Revised => ['who' => 'editor', 'task' => 'decideRevision'],
            SubmissionStatus::Accepted => ['who' => 'author', 'task' => 'chooseOption'],
            SubmissionStatus::OptionA => ['who' => 'author', 'task' => 'cameraReady'],
            SubmissionStatus::OptionB => ['who' => 'editor', 'task' => 'journal'],
            SubmissionStatus::CameraReady, SubmissionStatus::JournalAccepted => $scheduled ? ['who' => 'none', 'task' => 'done'] : ['who' => 'editor', 'task' => 'schedule'],
            SubmissionStatus::Rejected, SubmissionStatus::ReviewRejected => ['who' => 'none', 'task' => 'closed'],
        };
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
            $due = $waiting['task'] === 'revise' ? $this->revision_due_date : null;

            return ['mine' => true, 'task' => $waiting['task'], 'due' => $due?->toDateString(), 'daysLate' => $due?->isPast() ? (int) $due->diffInDays(today()) : 0];
        }

        return ['mine' => false, 'task' => match (true) {
            $waiting['who'] === 'none' => $waiting['task'], // done | closed
            $waiting['task'] === 'screenAfterDeadline' => 'editableUntilDeadline',
            $waiting['who'] === 'reviewer' || in_array($waiting['task'], ['assignReviewers', 'replaceReviewer'], true) => 'inReview',
            default => 'withEditor',
        }];
    }

    /** Number of papers in each queue: { screening: 3, ... } */
    public static function queueCounts(): array
    {
        return collect(self::QUEUES)->mapWithKeys(fn ($queue) => [$queue => static::query()->queue($queue)->count()])->all();
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
                ]),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /** The author's track choice is only initial: it locks once an editor has moved the paper, or when revising. */
    public function authorCanChangeTrack(): bool
    {
        return $this->status === SubmissionStatus::Submitted
            && ! $this->events()->where('action', SubmissionAction::TrackChanged)->exists();
    }

    /** Screening happens once the deadline has passed (files are final), one decision per round. */
    public function canBeScreened(): bool
    {
        return static::isClosed() && $this->status === SubmissionStatus::Submitted;
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
            'submittedAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'authors' => $this->authors->map->only('name', 'affiliation', 'email')->values(),
        ];

        if ($full) {
            $data += [
                'abstract' => ['en' => $this->abstract_en, 'th' => $this->abstract_th],
                'keywords' => ['en' => $this->keywords_en, 'th' => $this->keywords_th],
                'file' => ['name' => $this->file_name, 'size' => $this->file_size, 'url' => "/admin/submissions/{$this->id}/file"],
                'submitter' => $this->user?->only('id', 'name', 'email'),
                'publication' => [
                    'option' => $this->publish_option,
                    'journal' => $this->journal_name,
                    'cameraReady' => $this->camera_ready_path ? [
                        'name' => $this->camera_ready_name,
                        'size' => $this->camera_ready_size,
                        'at' => $this->camera_ready_at->toIso8601String(),
                        'url' => "/admin/submissions/{$this->id}/camera-ready",
                    ] : null,
                    'presentation' => $this->presentation_at ? [
                        'at' => $this->presentation_at->toIso8601String(),
                        'room' => $this->presentation_room,
                    ] : null,
                ],
            ];
        }

        return $data;
    }
}
