<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Meal preferences admin manages in Settings. users.meal and users.companion_meal hold the code;
 * OTHER means a typed preference (users.meal_other / companion_meal_other), e.g. a food allergy.
 */
class MealOption extends Model
{
    public const OTHER = 'other';

    /** Messages for rules() (translation keys, like AuthController::MESSAGES). */
    public const MESSAGES = ['meal.in' => 'validation.required', 'companion_meal.in' => 'validation.required'];

    protected $fillable = ['code', 'name_en', 'name_th', 'sort_order'];

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** People (registrants and companions) who chose this meal. */
    public function usersCount(): int
    {
        return User::where('meal', $this->code)->count() + User::where('companion_meal', $this->code)->count();
    }

    /** Codes a form may send, 'other' included. */
    public static function codes(): array
    {
        return [...static::pluck('code')->all(), self::OTHER];
    }

    /** A new short code from the English name (unique; never shown to users). */
    public static function newCode(string $nameEn): string
    {
        $base = Str::limit(Str::slug($nameEn, '_'), 30, '') ?: 'meal';
        $code = $base;
        for ($i = 2; static::where('code', $code)->exists() || $code === self::OTHER; $i++) {
            $code = "{$base}_{$i}";
        }

        return $code;
    }

    /**
     * Validation for the registrant's meal and the optional companion (sign-up, "My details", admin form).
     * `companion` is a tick: with it, the companion's name and meal are required.
     * Admin's form may leave the registrant's own meal empty (admin may not know it).
     */
    public static function rules(bool $required = true): array
    {
        $codes = static::codes();

        return [
            'meal' => [$required ? 'required' : 'nullable', Rule::in($codes)],
            'meal_other' => ['nullable', 'required_if:meal,'.self::OTHER, 'string', 'max:255'],
            'companion' => ['boolean'],
            'companion_name' => ['nullable', 'required_if:companion,true', 'string', 'max:255'],
            'companion_meal' => ['nullable', 'required_if:companion,true', Rule::in($codes)],
            'companion_meal_other' => ['nullable', 'required_if:companion_meal,'.self::OTHER, 'string', 'max:255'],
        ];
    }

    /** Validated input → user columns: typed text only with "Other", no companion fields without the tick. */
    public static function columns(array $data): array
    {
        $companion = (bool) ($data['companion'] ?? false);
        $typed = fn (?string $code, ?string $text) => $code === self::OTHER ? trim((string) $text) : null;

        return [
            'meal' => $data['meal'] ?? null,
            'meal_other' => $typed($data['meal'] ?? null, $data['meal_other'] ?? null),
            'companion_name' => $companion ? trim($data['companion_name']) : null,
            'companion_meal' => $companion ? $data['companion_meal'] : null,
            'companion_meal_other' => $companion ? $typed($data['companion_meal'], $data['companion_meal_other'] ?? null) : null,
        ];
    }

    /** Client shape: { code, name: { en, th } } for tr(). */
    public function toClient(): array
    {
        return ['code' => $this->code, 'name' => ['en' => $this->name_en, 'th' => $this->name_th]];
    }
}
