<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An internal note on a paper: admin and editors only, never shown to authors or reviewers. */
class SubmissionNote extends Model
{
    /** Minutes during which the writer may still delete a note; after that it stays as a record. */
    public const DELETE_WITHIN = 15;

    protected $fillable = ['body', 'pinned'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id && $this->created_at->addMinutes(self::DELETE_WITHIN)->isFuture();
    }

    public function toClient(User $viewer): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'pinned' => $this->pinned,
            'author' => $this->user ? ['name' => $this->user->name, 'role' => $this->user->accessRole()] : null,
            'at' => $this->created_at->toIso8601String(),
            'canDelete' => $this->canBeDeletedBy($viewer),
        ];
    }
}
