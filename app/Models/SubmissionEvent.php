<?php

namespace App\Models;

use App\Enums\SubmissionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'from_status', 'to_status', 'comment', 'meta'];

    protected function casts(): array
    {
        return ['action' => SubmissionAction::class, 'meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Client shape; the actor's name is included only for staff views. */
    public function toClient(bool $withActor): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'toStatus' => $this->to_status,
            'comment' => $this->comment,
            'meta' => $this->meta,
            'at' => $this->created_at->toIso8601String(),
            'actor' => $withActor ? $this->user?->only('name', 'role') : null,
        ];
    }
}
