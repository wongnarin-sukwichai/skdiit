<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A room presentations take place in (Presentations › Rooms, admin). Sessions pick a room from this list. */
class PresentationRoom extends Model
{
    protected $fillable = ['name_th', 'name_en', 'capacity', 'sort_order'];

    public function sessions(): HasMany
    {
        return $this->hasMany(PresentationSession::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => ['th' => $this->name_th, 'en' => $this->name_en],
            'name_th' => $this->name_th,
            'name_en' => $this->name_en,
            'capacity' => $this->capacity,
        ];
    }
}
