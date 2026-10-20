<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "I want to submit a paper too": an attend-only registrant's request, decided by admin on the Registrants page.
 * Approving switches users.participation to attend_submit. One pending request per registrant at a time.
 */
class AuthorRequest extends Model
{
    protected $fillable = ['user_id', 'year', 'reason', 'status', 'decided_by', 'decided_at', 'note'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /** Registrants may ask while the submission period has not closed (upcoming or open). */
    public static function canAskNow(): bool
    {
        return Submission::window()['phase'] !== 'closed';
    }

    /** The registrant's own view: their latest request this year, for "My details". */
    public static function forRegistrant(User $user): ?array
    {
        if ($user->role !== 'user' || $user->participation !== 'attend') {
            return null;
        }

        $latest = static::where('user_id', $user->id)->where('year', Submission::conferenceYear())->latest('id')->first();

        return [
            'status' => $latest?->status,
            'at' => $latest?->created_at->toIso8601String(),
            'note' => $latest?->status === 'rejected' ? $latest->note : null,
            'canAsk' => $latest?->status !== 'pending' && static::canAskNow(),
        ];
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'affiliation' => $this->user?->affiliation,
            'affiliation_other' => $this->user?->affiliation_other,
            'reason' => $this->reason,
            'status' => $this->status,
            'at' => $this->created_at->toIso8601String(),
            'decidedAt' => $this->decided_at?->toIso8601String(),
            'decidedBy' => $this->decider?->name,
            'note' => $this->note,
        ];
    }
}
