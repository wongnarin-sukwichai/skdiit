<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Partner journals (Settings › วารสาร, JournalOptionController). Editors or the journal role offer a paper to one of
 * them; the author who accepts submits on its site (url). Inactive journals stay for the papers already offered.
 */
class Journal extends Model
{
    protected $fillable = ['name', 'url', 'publisher', 'contact_name', 'contact_position', 'contact_email', 'contact_phone', 'terms', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** What authors and the selection list see (contacts are for staff, see toAdmin()). */
    public function toClient(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'url' => $this->url, 'terms' => $this->terms];
    }

    /** Every field, for the Settings tab and staff lists. */
    public function toAdmin(): array
    {
        return [
            ...$this->only('id', 'name', 'publisher', 'contact_name', 'contact_position', 'contact_email', 'contact_phone', 'active'),
            'url' => $this->url ?? '',
            'terms' => $this->terms ?? '',
        ];
    }
}
