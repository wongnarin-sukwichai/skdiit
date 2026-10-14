<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The author's answer to one reviewer (or to the editor when review_id is null) with a revised version. */
class RevisionResponse extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['version', 'review_id', 'body'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
