<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One reviewer's score (1-5) for one criterion of one evaluation. */
class ReviewScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['review_criterion_id', 'score'];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(ReviewCriterion::class, 'review_criterion_id');
    }
}
