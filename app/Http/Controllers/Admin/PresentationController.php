<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Http\Controllers\Controller;
use App\Models\PresentationRoom;
use App\Models\PresentationSession;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\Track;
use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The presentation schedule (2026-11-12, replaces the mock page): admin keeps the rooms and the sessions and puts
 * papers in running order; editors see it, and put a paper in a session from its own page (assign()).
 * Papers that can present: camera-ready approved, or gone to a journal (Submission::canBeScheduled()).
 */
class PresentationController extends Controller
{
    private const MESSAGES = [
        'required' => 'validation.required',
        'max' => 'validation.too_long',
        'integer' => 'validation.integer',
        'date_format' => 'validation.date',
        'end_time.after' => 'validation.end_after_start',
        'date.in' => 'admin.presentations.dayOutside',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Presentations', [
            'sessions' => PresentationSession::currentYear()->ordered()->with(['room', 'track', 'papers.authors'])->get()
                ->map(fn (PresentationSession $session) => $session->toClient(withPapers: true)),
            'rooms' => PresentationRoom::ordered()->withCount('sessions')->get()
                ->map(fn (PresentationRoom $room) => [...$room->toClient(), 'sessions' => $room->sessions_count]),
            'tracks' => Track::ordered()->get()->map->toClient(),
            // Can present but has no session yet
            'waiting' => Submission::currentYear()->schedulable()->whereNull('presentation_session_id')->with('authors', 'track')->orderBy('code')->get()
                ->map(fn (Submission $paper) => [
                    'id' => $paper->id,
                    'code' => $paper->code,
                    'title' => ['en' => $paper->title_en, 'th' => $paper->title_th ?: $paper->title_en],
                    'authors' => $paper->authors->pluck('name')->join(', '),
                    'track' => $paper->track?->toClient(),
                    'journal' => $paper->status->value === 'journal_accepted',
                ]),
            'days' => static::days(),
            'conferenceDays' => static::conferenceDays(),
            'canEdit' => $request->user()->moduleAccess('presentations') === 'full',
            'unnotified' => PresentationSession::unnotified()->count(),
            'needSlides' => static::needSlides()->count(),
        ]);
    }

    /**
     * Days with presentations (2026-11-12): kept on the Presentations page (a keynote day may have none), any days,
     * not only consecutive ones; until admin changes them, the conference days.
     */
    public static function days(): array
    {
        $saved = json_decode((string) Setting::get('presentation_days'), true);

        return is_array($saved) ? $saved : static::conferenceDays();
    }

    /** The conference days (Settings › Conference: date to end date). */
    public static function conferenceDays(): array
    {
        $conference = SiteContent::all()['conference'] ?? [];
        if (empty($conference['date'])) {
            return [];
        }
        $from = Carbon::parse($conference['date']);
        $to = ! empty($conference['endDate']) ? Carbon::parse($conference['endDate']) : $from->copy();

        $days = [];
        for ($day = $from->copy(); $day->lte($to) && count($days) < 14; $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    // ---- Days ----

    public function storeDay(Request $request): RedirectResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']], self::MESSAGES)['date'];
        $days = collect(static::days())->push($date)->unique()->sort()->values()->all();
        Setting::put('presentation_days', json_encode($days));

        return back()->with('success', 'admin.presentations.toast.daySaved');
    }

    /** A day with sessions cannot go: move or delete its sessions first. */
    public function destroyDay(string $date): RedirectResponse
    {
        if (PresentationSession::currentYear()->whereDate('date', $date)->exists()) {
            return back()->with('error', 'admin.presentations.dayInUse');
        }
        Setting::put('presentation_days', json_encode(collect(static::days())->reject(fn ($day) => $day === $date)->values()->all()));

        return back()->with('success', 'admin.presentations.toast.dayRemoved');
    }

    // ---- Rooms ----

    public function storeRoom(Request $request): RedirectResponse
    {
        PresentationRoom::create([...$this->room($request), 'sort_order' => (int) PresentationRoom::max('sort_order') + 1]);

        return back()->with('success', 'admin.presentations.toast.roomSaved');
    }

    public function updateRoom(Request $request, PresentationRoom $room): RedirectResponse
    {
        $room->update($this->room($request));
        // Papers keep the room name in their slot: refresh the sessions in this room
        $room->sessions()->currentYear()->get()->each(fn (PresentationSession $session) => $session->syncPapers());

        return back()->with('success', 'admin.presentations.toast.roomSaved');
    }

    /** Only a room no session uses can be deleted. */
    public function destroyRoom(PresentationRoom $room): RedirectResponse
    {
        abort_if($room->sessions()->exists(), 403);
        $room->delete();

        return back()->with('success', 'admin.presentations.toast.roomDeleted');
    }

    private function room(Request $request): array
    {
        return $request->validate([
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ], self::MESSAGES);
    }

    // ---- Sessions ----

    public function storeSession(Request $request): RedirectResponse
    {
        $session = new PresentationSession([...$this->session($request), 'year' => Submission::conferenceYear()]);
        $this->guardClash($session);
        $session->save();

        return back()->with('success', 'admin.presentations.toast.saved');
    }

    public function updateSession(Request $request, PresentationSession $session): RedirectResponse
    {
        $session->fill($this->session($request));
        $this->guardClash($session);
        $session->save();
        $session->syncPapers(); // new day, time, room or minutes: every paper's slot follows

        return back()->with('success', 'admin.presentations.toast.saved');
    }

    /** Its papers go back to the waiting list (their slot is cleared). */
    public function destroySession(Request $request, PresentationSession $session): RedirectResponse
    {
        DB::transaction(function () use ($request, $session) {
            $session->papers()->get()->each(fn (Submission $paper) => $this->clearSlot($paper, $request));
            $session->delete();
        });

        return back()->with('success', 'admin.presentations.toast.removed');
    }

    private function session(Request $request): array
    {
        $days = static::days();

        return $request->validate([
            'date' => ['required', 'date_format:Y-m-d', ...($days ? [Rule::in($days)] : [])],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'presentation_room_id' => ['required', 'integer', 'exists:presentation_rooms,id'],
            'track_id' => ['nullable', 'integer', 'exists:tracks,id'],
            'chair' => ['nullable', 'string', 'max:255'],
            'minutes' => ['required', 'integer', 'min:5', 'max:120'],
        ], self::MESSAGES);
    }

    private function guardClash(PresentationSession $session): void
    {
        if ($session->clashes()) {
            throw ValidationException::withMessages(['presentation_room_id' => 'admin.presentations.roomTaken']);
        }
    }

    // ---- Papers in sessions ----

    /**
     * Put a paper in a session (last in its running order), move it to another one, or take it out (session_id null).
     * Used by the Presentations page (admin) and the paper page (editors). The author is emailed later, by notify().
     */
    public function assign(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeScheduled(), 403);
        $data = $request->validate(['session_id' => ['nullable', 'integer', 'exists:presentation_sessions,id']]);

        $old = $submission->presentationSession;
        $new = isset($data['session_id']) ? PresentationSession::currentYear()->findOrFail($data['session_id']) : null;
        if ($new && $new->id !== $old?->id && $new->papers()->count() >= $new->capacity()) {
            throw ValidationException::withMessages(['session_id' => 'admin.presentations.full']);
        }

        DB::transaction(function () use ($request, $submission, $old, $new) {
            if (! $new) {
                $this->clearSlot($submission, $request);
            } elseif ($new->id !== $old?->id) {
                $submission->forceFill(['presentation_session_id' => $new->id, 'presentation_order' => (int) $new->papers()->max('presentation_order') + 1])->save();
                $new->syncPapers();
            }
            if ($old && $old->id !== $new?->id) {
                $old->syncPapers(); // close the gap it left
            }
        });

        return back()->with('success', $new ? 'publication.scheduleSaved' : 'admin.presentations.toast.unscheduled');
    }

    /** Swap a paper with the one before or after it in its session. */
    public function move(Request $request, Submission $submission): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $session = $submission->presentationSession;
        abort_unless((bool) $session, 404);

        $ids = $session->papers()->pluck('id')->all();
        $from = array_search($submission->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;
        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            DB::transaction(function () use ($ids, $session) {
                foreach ($ids as $i => $id) {
                    Submission::whereKey($id)->update(['presentation_order' => $i + 1]);
                }
                $session->syncPapers();
            });
        }

        return back();
    }

    /**
     * "Remind about slides" (2026-11-13): email every author whose paper has a session, no slides yet and can
     * still upload (Submission::canUploadSlides()). Logged as slides_reminded on each paper.
     */
    public function remindSlides(Request $request): RedirectResponse
    {
        $papers = static::needSlides();
        $papers->each(fn (Submission $paper) => $paper->log(SubmissionAction::SlidesReminded, $request->user(), meta: static::slidesReminderMeta($paper)));

        return back()->with('success', ['key' => 'admin.presentations.toast.slidesReminded', 'params' => ['n' => $papers->count()]]);
    }

    /** How many presenters would get the slides reminder, and the first one's email as it will go out (2026-11-13). */
    public function remindSlidesPreview(): JsonResponse
    {
        $papers = static::needSlides();

        return response()->json([
            'count' => $papers->count(),
            'preview' => $papers->first()?->previewMail(SubmissionAction::SlidesReminded, static::slidesReminderMeta($papers->first())),
        ]);
    }

    private static function slidesReminderMeta(Submission $paper): array
    {
        return [
            'at' => $paper->presentation_at->toIso8601String(),
            'room' => $paper->presentation_room,
            'due' => $paper->slidesUntil()->toDateString(),
            'due_time' => $paper->slidesUntil()->format('H:i'),
        ];
    }

    /** This year's papers in a session, without slides, still open for them. */
    private static function needSlides(): Collection
    {
        return Submission::currentYear()->whereNotNull('presentation_session_id')->whereNull('slides_path')->get()
            ->filter(fn (Submission $paper) => $paper->canUploadSlides())->values();
    }

    /** "Notify authors" (2026-11-13): one email per paper whose slot changed since its last notice. */
    public function notify(Request $request): RedirectResponse
    {
        $count = PresentationSession::notifyAuthors($request->user());

        return back()->with('success', ['key' => 'admin.presentations.toast.notified', 'params' => ['n' => $count]]);
    }

    private function clearSlot(Submission $paper, Request $request): void
    {
        $paper->forceFill(['presentation_session_id' => null, 'presentation_order' => null, 'presentation_at' => null, 'presentation_room' => null, 'presentation_notified' => null])->save();
        $paper->log(SubmissionAction::PresentationScheduled, $request->user(), meta: ['at' => null, 'room' => null]);
    }

    /** The public schedule (Front/Schedule): sessions with their papers and times, no contact details. */
    public static function publicSchedule(): array
    {
        return PresentationSession::currentYear()->ordered()->with(['room', 'track', 'papers.authors'])->get()
            ->map(fn (PresentationSession $session) => $session->toClient(withPapers: true))->all();
    }
}
