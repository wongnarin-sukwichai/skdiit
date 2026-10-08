<?php

namespace App\Models;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
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

    /** New papers are accepted, and authors may edit theirs, until the deadline (all lock together). */
    public static function isOpen(): bool
    {
        return ! static::deadline()?->isPast();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
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
        return ! static::isOpen() && $this->status === SubmissionStatus::Submitted;
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
                'submitter' => $this->user?->only('name', 'email'),
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
