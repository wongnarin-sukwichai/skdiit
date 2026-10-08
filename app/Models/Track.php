<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Track extends Model
{
    protected $fillable = ['name_en', 'name_th', 'sort_order'];

    /** Reviewers who can be assigned papers from this track. */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reviewer_track');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** Shape used by the Vue client: bilingual name as { en, th } for tr(). */
    public function toClient(): array
    {
        return ['id' => $this->id, 'name' => ['en' => $this->name_en, 'th' => $this->name_th]];
    }
}
