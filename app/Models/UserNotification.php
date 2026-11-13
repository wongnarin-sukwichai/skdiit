<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One notification for one person (2026-11-13). Made by App\Support\Notifier from the paper history and from
 * failed emails; the client turns `kind` + `params` into the text (notifications.kind.* in the i18n files).
 */
class UserNotification extends Model
{
    protected $fillable = ['user_id', 'submission_id', 'kind', 'params', 'href', 'read_at'];

    protected function casts(): array
    {
        return ['params' => 'array', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Shared with every backend page (bell, home card, Notifications page): the latest ones and the unread count. */
    public static function forClient(?User $user, int $limit = 50): ?array
    {
        if (! $user || ! $user->modules()) {
            return null;
        }

        return [
            'items' => static::where('user_id', $user->id)->latest('id')->take($limit)->get()->map->toClient()->values(),
            'unread' => static::where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'params' => $this->params ?? [],
            'href' => $this->href,
            'at' => $this->created_at->toIso8601String(),
            'read' => (bool) $this->read_at,
        ];
    }
}
