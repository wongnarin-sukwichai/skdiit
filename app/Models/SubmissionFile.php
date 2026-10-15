<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded file of a paper, by kind: manuscript (the PDF of a version), word (the same version as Word),
 * turnitin (similarity report, PDF), supplementary (any other file, optional), response (letter sent with a revision).
 */
class SubmissionFile extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['version', 'kind', 'stage', 'round', 'path', 'name', 'size', 'user_id'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function isPdf(): bool
    {
        return str_ends_with(strtolower($this->name), '.pdf');
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size' => $this->size,
            'isPdf' => $this->isPdf(),
            'kind' => $this->kind,
            'at' => $this->created_at?->toIso8601String(),
            'url' => "/admin/submission-files/{$this->id}",
        ];
    }
}
