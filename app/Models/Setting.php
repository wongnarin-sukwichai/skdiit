<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value system settings. Use Setting::get('key') / Setting::put('key', $value).
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** Values used until admin saves a setting. */
    public const DEFAULTS = [
        'upload_max_mb' => 10,
        'submission_deadline' => null, // 'Y-m-d H:i' in the app timezone; null = no deadline
    ];

    public static function get(string $key): mixed
    {
        return static::find($key)?->value ?? self::DEFAULTS[$key] ?? null;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Largest upload PHP accepts, in MB (the smaller of upload_max_filesize and post_max_size). */
    public static function serverUploadLimitMb(): int
    {
        $toMb = function (string $size): int {
            $value = (int) $size;

            return match (strtoupper(substr(trim($size), -1))) {
                'G' => $value * 1024,
                'K' => intdiv($value, 1024),
                'M' => $value,
                default => intdiv($value, 1024 * 1024),
            };
        };

        return max(1, min($toMb(ini_get('upload_max_filesize')), $toMb(ini_get('post_max_size'))));
    }
}
