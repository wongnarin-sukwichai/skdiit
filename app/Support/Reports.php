<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Affiliation;
use App\Models\Journal;
use App\Models\Review;
use App\Models\ReviewCriterion;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reports and the home-page people numbers (2026-11-13, real; agreed with the user): this conference year's
 * registrants, papers and reviews. Labels are sent as { en, th } (or as codes the client already knows).
 */
class Reports
{
    public static function all(): array
    {
        return ['registrants' => static::registrants(), 'papers' => static::papers(), 'reviews' => static::reviews()];
    }

    /** This year's registrants (sign-ups and attend-only people admin added). */
    private static function people()
    {
        return User::where('role', 'user')->where('registered_year', Submission::conferenceYear());
    }

    /** Sign-ups per day for the last N days: [{ date, value }]. */
    public static function signups(int $days): array
    {
        $from = today()->subDays($days - 1);
        $counts = static::people()->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $counts) {
            $date = $from->copy()->addDays($i)->toDateString();

            return ['date' => $date, 'value' => (int) ($counts[$date] ?? 0)];
        })->all();
    }

    /** Home page: registrants today, in all, and those who never signed in. */
    public static function peopleNumbers(): array
    {
        return [
            'today' => static::people()->whereDate('created_at', today())->count(),
            'total' => static::people()->count(),
            'notSignedIn' => static::people()->whereNull('email_verified_at')->whereNull('suspended_at')->count(),
            'trend' => static::signups(14),
        ];
    }

    private static function registrants(): array
    {
        $people = static::people()->get(['affiliation', 'affiliation_other', 'participation', 'email_verified_at', 'meal', 'meal_other', 'companion_name', 'companion_meal', 'companion_meal_other']);

        // Meals of the registrants and their companions: [[code, other text, n]]
        $meals = $people->map(fn ($p) => [$p->meal, $p->meal === 'other' ? $p->meal_other : null])
            ->merge($people->filter(fn ($p) => $p->companion_name)->map(fn ($p) => [$p->companion_meal, $p->companion_meal === 'other' ? $p->companion_meal_other : null]))
            ->filter(fn ($m) => $m[0])
            ->countBy(fn ($m) => $m[0].'|'.($m[1] ?? ''))
            ->map(fn ($n, $key) => [...explode('|', $key, 2), $n])->sortByDesc(2)->values();

        return [
            'kpis' => [
                'total' => $people->count(),
                'papers' => static::people()->whereHas('submissions', fn ($q) => $q->currentYear())->count(),
                'companions' => $people->filter(fn ($p) => $p->companion_name)->count(),
                'notSignedIn' => $people->whereNull('email_verified_at')->count(),
            ],
            'trend' => static::signups(30),
            'byAffiliation' => static::affiliationCounts($people),
            'meals' => $meals,
            'companions' => $people->filter(fn ($p) => $p->companion_name)->count(),
        ];
    }

    /** [[label { en, th } , n]] by affiliation, "Other" by the typed name. */
    private static function affiliationCounts(Collection $people): Collection
    {
        $names = Affiliation::all()->keyBy('code');

        return $people->countBy(fn ($p) => $p->affiliation === Affiliation::OTHER ? 'other:'.trim((string) $p->affiliation_other) : (string) $p->affiliation)
            ->map(function ($n, $key) use ($names) {
                if (str_starts_with($key, 'other:')) {
                    $label = substr($key, 6) ?: '—';

                    return [['en' => $label, 'th' => $label], $n];
                }
                $a = $names[$key] ?? null;

                return [$a ? ['en' => $a->name_en, 'th' => $a->name_th] : ['en' => $key ?: '—', 'th' => $key ?: '—'], $n];
            })->sortByDesc(1)->values();
    }

    private static function papers(): array
    {
        $papers = Submission::currentYear()->with('user:id,affiliation,affiliation_other', 'track', 'journal')->get();
        $count = fn (array $statuses) => $papers->filter(fn ($p) => in_array($p->status, $statuses, true))->count();
        $accepted = $count([SubmissionStatus::Accepted, SubmissionStatus::CameraReady]);
        $rejected = $count([SubmissionStatus::Rejected, SubmissionStatus::ReviewRejected]);

        $tracks = Track::ordered()->get();
        $byTrack = $tracks->map(fn (Track $track) => [$track->toClient()['name'], $papers->where('track_id', $track->id)->count()])
            ->push([null, $papers->whereNull('track_id')->count()])->filter(fn ($row) => $row[1] > 0)->values();

        // Journals: offered (ever offered this year's paper) and accepted by the author
        $offered = DB::table('submission_events')->join('submissions', 'submissions.id', '=', 'submission_events.submission_id')
            ->where('submissions.year', Submission::conferenceYear())->where('submission_events.action', 'journal_screened')
            ->where('submission_events.to_status', 'journal_selected')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(submission_events.meta, '$.journal_id')) as journal, count(distinct submission_events.submission_id) as n")
            ->groupBy('journal')->pluck('n', 'journal');
        $byJournal = Journal::orderBy('sort_order')->get()
            ->map(fn (Journal $j) => ['name' => $j->name, 'offered' => (int) ($offered[(string) $j->id] ?? 0), 'accepted' => $papers->where('journal_id', $j->id)->where('status', SubmissionStatus::JournalAccepted)->count()])
            ->filter(fn ($row) => $row['offered'] || $row['accepted'])->values();

        return [
            'kpis' => [
                'submitted' => $papers->count(),
                'inReview' => $count([SubmissionStatus::InReview, SubmissionStatus::ReviewRevision, SubmissionStatus::Revised]),
                'accepted' => $accepted,
                'rejected' => $rejected,
            ],
            'byTrack' => $byTrack,
            'byStatus' => collect(SubmissionStatus::cases())->map(fn ($s) => [$s->value, $count([$s])])->values(),
            'routes' => [['journal', $count([SubmissionStatus::JournalAccepted])], ['conference', $accepted]],
            'byAffiliation' => static::affiliationCounts($papers->pluck('user')->filter()),
            'byJournal' => $byJournal,
        ];
    }

    private static function reviews(): array
    {
        $reviews = Review::with('reviewer.tracks', 'scores')
            ->whereHas('submission', fn ($q) => $q->currentYear())->get();
        $sent = $reviews->whereNotNull('submitted_at');
        $late = fn (Review $r) => ! $r->submitted_at && ! $r->unavailable_at && $r->due_date && $r->due_date->lt(today());
        $avgDays = fn (Collection $rows) => $rows->count() ? round($rows->avg(fn (Review $r) => $r->created_at->diffInHours($r->submitted_at) / 24), 1) : 0;

        $criteria = ReviewCriterion::orderBy('sort_order')->get()->map(function (ReviewCriterion $c) use ($sent) {
            $scores = $sent->flatMap->scores->where('review_criterion_id', $c->id);

            return [['en' => $c->name_en, 'th' => $c->name_th], $scores->count() ? round($scores->avg('score'), 2) : null];
        })->values();

        return [
            'kpis' => ['assigned' => $reviews->count(), 'done' => $sent->count(), 'avgDays' => $avgDays($sent), 'overdue' => $reviews->filter($late)->count()],
            'recommendations' => collect(['accept', 'revise', 'reject'])->map(fn ($r) => [$r, $sent->filter(fn (Review $x) => $x->recommendation?->value === $r)->count()])->values(),
            'reviewers' => $reviews->groupBy('reviewer_id')->map(function (Collection $mine) use ($avgDays, $late) {
                $reviewer = $mine->first()->reviewer;

                return [
                    'name' => $reviewer?->name ?? '—',
                    'tracks' => $reviewer?->tracks->map(fn (Track $t) => $t->toClient()['name'])->values() ?? [],
                    'assigned' => $mine->count(),
                    'done' => $mine->whereNotNull('submitted_at')->count(),
                    'avgDays' => $avgDays($mine->whereNotNull('submitted_at')),
                    'late' => $mine->filter($late)->count(),
                ];
            })->values(),
            'criteria' => $criteria,
        ];
    }
}
