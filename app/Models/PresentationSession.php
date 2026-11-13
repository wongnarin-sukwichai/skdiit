<?php

namespace App\Models;

use App\Enums\SubmissionAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A presentation session (2026-11-12): one day, one time span, one room, a track, a chair (typed in) and the minutes
 * each paper gets. Its papers run in presentation_order; each paper's slot (submissions.presentation_at / room)
 * is worked out from the session by syncPapers(), which the slides cut-off and the public schedule read. Authors are
 * emailed only when staff press "Notify authors" (notifyAuthors(), 2026-11-13), for slots that changed since.
 */
class PresentationSession extends Model
{
    protected $fillable = ['year', 'date', 'start_time', 'end_time', 'presentation_room_id', 'track_id', 'chair', 'minutes'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(PresentationRoom::class, 'presentation_room_id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function papers(): HasMany
    {
        return $this->hasMany(Submission::class)->orderBy('presentation_order')->orderBy('id');
    }

    public function scopeCurrentYear(Builder $query): void
    {
        $query->where('year', Submission::conferenceYear());
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('date')->orderBy('start_time')->orderBy('presentation_room_id');
    }

    private static function minutesOf(string $hhmm): int
    {
        return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
    }

    /** Minutes in the session's time span. */
    public function length(): int
    {
        return static::minutesOf($this->end_time) - static::minutesOf($this->start_time);
    }

    /** How many papers fit (the span divided by the minutes per paper). */
    public function capacity(): int
    {
        return intdiv(max(0, $this->length()), max(1, $this->minutes));
    }

    /** Another session in the same room on the same day whose time overlaps this one. */
    public function clashes(): bool
    {
        return static::where('year', $this->year)->whereDate('date', $this->date)
            ->where('presentation_room_id', $this->presentation_room_id)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->id))
            ->where('start_time', '<', $this->end_time)->where('end_time', '>', $this->start_time)
            ->exists();
    }

    /**
     * Number the papers 1..n in their order and work out each one's slot from the session. Nothing is emailed here:
     * arranging a session moves many slots, so authors hear once, from notifyAuthors().
     */
    public function syncPapers(): void
    {
        $this->loadMissing('room');
        $start = Carbon::parse($this->date->toDateString().' '.$this->start_time);
        $room = $this->room->name_th;

        $this->papers()->get()->values()->each(function (Submission $paper, int $i) use ($start, $room) {
            $at = $start->copy()->addMinutes($i * $this->minutes);
            $paper->forceFill(['presentation_order' => $i + 1, 'presentation_at' => $at, 'presentation_room' => $room])->save();
        });
    }

    /** "Y-m-d H:i|room" of a paper's current slot, compared with submissions.presentation_notified. */
    public static function slotKey(Submission $paper): ?string
    {
        return $paper->presentation_at ? $paper->presentation_at->format('Y-m-d H:i').'|'.$paper->presentation_room : null;
    }

    /** This year's papers in a session whose slot the author has not been emailed yet. */
    public static function unnotified(): Collection
    {
        return Submission::currentYear()->whereNotNull('presentation_session_id')->whereNotNull('presentation_at')->get()
            ->filter(fn (Submission $paper) => static::slotKey($paper) !== $paper->presentation_notified)->values();
    }

    /** Email every author whose slot changed since the last notice (logged as "presentation scheduled"). Returns how many. */
    public static function notifyAuthors(?User $by): int
    {
        $papers = static::unnotified();
        $papers->each(function (Submission $paper) use ($by) {
            $paper->forceFill(['presentation_notified' => static::slotKey($paper)])->save();
            $paper->log(SubmissionAction::PresentationScheduled, $by, meta: ['at' => $paper->presentation_at->toIso8601String(), 'room' => $paper->presentation_room]);
        });

        return $papers->count();
    }

    /** One line for pickers: "27 ม.ค. 09:00–10:30 · room". */
    public function toClient(bool $withPapers = false): array
    {
        $this->loadMissing('room', 'track');
        $count = $this->relationLoaded('papers') ? $this->papers->count() : $this->papers()->count();

        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'start' => $this->start_time,
            'end' => $this->end_time,
            'room' => $this->room->toClient(),
            'track' => $this->track?->toClient(),
            'chair' => $this->chair,
            'minutes' => $this->minutes,
            'length' => $this->length(),
            'capacity' => $this->capacity(),
            'count' => $count,
            ...($withPapers ? ['papers' => $this->papers->map(fn (Submission $paper) => [
                'id' => $paper->id,
                'code' => $paper->code,
                'title' => ['en' => $paper->title_en, 'th' => $paper->title_th ?: $paper->title_en],
                'authors' => $paper->relationLoaded('authors') ? $paper->authors->pluck('name')->join(', ') : '',
                'at' => $paper->presentation_at?->format('H:i'),
                'slides' => (bool) $paper->slides_path,
                'notified' => static::slotKey($paper) === $paper->presentation_notified,
            ])->values()] : []),
        ];
    }
}
