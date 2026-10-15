<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A scoring criterion admin manages in Settings; reviewers score each one from 1 to 5. */
class ReviewCriterion extends Model
{
    protected $table = 'review_criteria';

    protected $fillable = ['name_th', 'name_en', 'description_th', 'description_en', 'sort_order'];

    public const MIN = 1;

    public const MAX = 5;

    public function scores(): HasMany
    {
        return $this->hasMany(ReviewScore::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** Shape used by the Vue client: bilingual { en, th } for tr(). */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => ['en' => $this->name_en, 'th' => $this->name_th],
            'description' => $this->description_th || $this->description_en
                ? ['en' => $this->description_en ?: $this->description_th, 'th' => $this->description_th ?: $this->description_en]
                : null,
        ];
    }
}
