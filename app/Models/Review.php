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
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
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
            $this->isOverdue() => 'overdue',
            default => 'pending',
        };
    }

    /** Client shape. The reviewer's identity is included only for staff views. */
    public function toClient(bool $withReviewer): array
    {
        return [
            'id' => $this->id,
            'round' => $this->round,
            'dueDate' => $this->due_date->toDateString(),
            'state' => $this->state(),
            'recommendation' => $this->recommendation?->value,
            'comment' => $this->comment,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'reviewer' => $withReviewer ? $this->reviewer?->only('id', 'name', 'email') : null,
        ];
    }
}
