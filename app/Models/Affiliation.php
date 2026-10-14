<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Affiliations admin manages in Settings. users.affiliation holds the code;
 * OTHER means the registrant typed their own name (users.affiliation_other).
 */
class Affiliation extends Model
{
    public const OTHER = 'other';

    protected $fillable = ['code', 'name_en', 'name_th', 'sort_order'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'affiliation', 'code');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** Codes a form may send, 'other' included. */
    public static function codes(): array
    {
        return [...static::pluck('code')->all(), self::OTHER];
    }

    /** A new short code from the English name (unique; never shown to users). */
    public static function newCode(string $nameEn): string
    {
        $base = Str::limit(Str::slug($nameEn), 30, '') ?: 'aff';
        $code = $base;
        for ($i = 2; static::where('code', $code)->exists() || $code === self::OTHER; $i++) {
            $code = "{$base}-{$i}";
        }

        return $code;
    }

    /** Client shape: { code, name: { en, th } } for tr(). */
    public function toClient(): array
    {
        return ['code' => $this->code, 'name' => ['en' => $this->name_en, 'th' => $this->name_th]];
    }
}
