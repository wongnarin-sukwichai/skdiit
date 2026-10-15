<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An email sent to a group of people (App\Support\Audience), kept as history with who sent it. */
class Announcement extends Model
{
    protected $fillable = ['audience', 'subject', 'body', 'recipients'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'audience' => $this->audience,
            'subject' => $this->subject,
            'body' => $this->body,
            'recipients' => $this->recipients,
            'by' => $this->user?->name,
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
