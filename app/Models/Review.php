<?php

namespace App\Models;

use App\Enums\ReviewRecommendation;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['reviewer_id', 'assigned_by', 'round', 'due_date'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'recommendation' => ReviewRecommendation::class,
            'submitted_at' => 'datetime',
            'reminded_at' => 'datetime',
            'draft_saved_at' => 'datetime',
            'conflict_declared_at' => 'datetime',
            'unavailable_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function remindedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reminded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /** The reviewer told the editors they cannot do it (the editors remove them and assign someone else). */
    public function isUnavailable(): bool
    {
        return ! $this->isSubmitted() && $this->unavailable_at !== null;
    }

    /** Past the due date (the whole due day counts) and not yet submitted. */
    public function isOverdue(): bool
    {
        return ! $this->isSubmitted() && $this->due_date->endOfDay()->isPast();
    }

    /** The editor has decided this round (or a newer round started) before this review came in. */
    public function isClosed(): bool
    {
        return ! $this->isSubmitted() && (
            $this->submission->status !== SubmissionStatus::InReview || $this->round !== $this->submission->review_round
        );
    }

    /** 'submitted' | 'closed' | 'overdue' | 'pending' */
    public function state(): string
    {
        return match (true) {
            $this->isSubmitted() => 'submitted',
            $this->isClosed() => 'closed',
            $this->isUnavailable() => 'unavailable',
            $this->isOverdue() => 'overdue',
            default => 'pending',
        };
    }

    /** Client shape. The reviewer's identity is included only for staff views. */
    /**
     * $withReviewer = staff view: the evaluation shows only once submitted (a draft stays the reviewer's).
     * Without it = the reviewer's own view: the draft comes back to keep editing.
     */
    public function toClient(bool $withReviewer): array
    {
        $visible = ! $withReviewer || $this->isSubmitted();

        return [
            'id' => $this->id,
            'round' => $this->round,
            'dueDate' => $this->due_date->toDateString(),
            'state' => $this->state(),
            'recommendation' => $visible ? $this->recommendation?->value : null,
            'comment' => $visible ? $this->comment : null,
            // Confidential: for the editors only (never shown to authors)
            'commentToEditor' => $visible ? $this->comment_to_editor : null,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'draftSavedAt' => $withReviewer ? null : $this->draft_saved_at?->toIso8601String(),
            'conflictDeclaredAt' => $this->conflict_declared_at?->toIso8601String(),
            'unavailable' => $this->unavailable_at ? ['at' => $this->unavailable_at->toIso8601String(), 'reason' => $this->unavailable_reason] : null,
            'attachment' => $this->attachment_path ? [
                'name' => $this->attachment_name,
                'size' => $this->attachment_size,
                'url' => "/admin/review-files/{$this->id}",
            ] : null,
            'reviewer' => $withReviewer ? $this->reviewer?->only('id', 'name', 'email') : null,
            // Staff only: the latest reminder and who sent it
            'reminder' => $withReviewer && $this->reminded_at ? [
                'at' => $this->reminded_at->toIso8601String(),
                'by' => $this->remindedBy?->name,
                'count' => $this->reminders,
            ] : null,
        ];
    }
}
