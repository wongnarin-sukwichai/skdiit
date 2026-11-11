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
            'slides_at' => 'datetime',
            'presentation_at' => 'datetime',
            'journal_answer_due' => 'date',
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

    /** Version number of the current manuscript (the latest PDF). */
    public function currentVersion(): int
    {
        return max(1, (int) $this->files()->where('kind', 'manuscript')->max('version'));
    }

    /**
     * Files that come with a manuscript version: ['word' => file, 'turnitin' => file, 'supplementary' => [files]].
     * A Turnitin report left out of a revision keeps the earlier one as the current report.
     */
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
            SubmissionStatus::CameraReady, SubmissionStatus::JournalAccepted => match (true) {
                ! $scheduled => ['who' => 'editor', 'task' => 'schedule'],
                ! $this->slides_path => ['who' => 'author', 'task' => 'slides'],
                default => ['who' => 'none', 'task' => 'done'],
            },
            SubmissionStatus::Rejected, SubmissionStatus::ReviewRejected => ['who' => 'none', 'task' => 'closed'],
        };
    }

    /** `had_revision`: the author has sent a revision before (the author's journey keeps the revision step). */
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
        if ($trackId && $trackId !== $this->track_id) {
            $fromTrack = $this->track;
            $this->track_id = $trackId;
            $this->save();
            $this->log(SubmissionAction::TrackChanged, $by, null, null, ['from' => $fromTrack?->toClient()['name'], 'to' => $this->load('track')->track->toClient()['name']]);
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

    /** Offers not answered by their due date go to the conference track (run on page loads and by the scheduler). */
    public static function expireJournalOffers(): int
    {
        $expired = static::where('status', SubmissionStatus::JournalSelected)->whereDate('journal_answer_due', '<', today())->with('journal')->get();
        $expired->each->answerJournalOffer('expired', null);

        return $expired->count();
    }

    /** Remind authors whose offer is due within two days (once per offer; scheduler only, it sends email). */
    public static function remindJournalOffers(): int
    {
        $due = static::where('status', SubmissionStatus::JournalSelected)->whereNull('journal_reminded_at')
            ->whereDate('journal_answer_due', '>=', today())->whereDate('journal_answer_due', '<=', today()->addDays(2))->with('journal')->get();
        $due->each(function (Submission $submission) {
            $submission->forceFill(['journal_reminded_at' => now()])->save();
            $submission->log(SubmissionAction::JournalOfferReminded, null, null, null, ['journal' => $submission->journal?->name, 'due' => $submission->journal_answer_due->toDateString()]);
        });

        return $due->count();
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
            'submittedAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'authors' => $this->authors->map->only('name', 'affiliation', 'email')->values(),
            // First gate: the journal offered (name, link, terms) and the last day to answer
            'journal' => $this->journal?->toClient(),
            'journalAnswerDue' => $this->journal_answer_due?->toDateString(),
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
