<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Academic titles admin manages in Settings (shown before staff and reviewer names). */
class AcademicTitle extends Model
{
    protected $fillable = ['short_en', 'short_th', 'name_en', 'name_th', 'sort_order'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
            'short' => ['en' => $this->short_en, 'th' => $this->short_th],
            'name' => ['en' => $this->name_en, 'th' => $this->name_th],
        ];
    }
}
