<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A downloadable file on the public "Documents" page (Settings › Website content). */
class SiteDocument extends Model
{
    protected $fillable = ['name_th', 'name_en', 'path', 'file_name', 'size', 'sort_order'];

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => ['en' => $this->name_en, 'th' => $this->name_th],
            'type' => strtoupper(pathinfo($this->file_name, PATHINFO_EXTENSION)),
            'size' => $this->size,
            'url' => "/documents/{$this->id}",
        ];
    }
}
