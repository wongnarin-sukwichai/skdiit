<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The journal's first gate (journal role; admin may do every journal action, editors only look on the paper page):
 *   1. selection after the submission deadline: selected -> the author submits on the journal's site,
 *      not selected -> the conference track (editor screening);
 *   2. the journal's final answer for a selected paper: accepted (presents, no camera-ready) or rejected -> conference track.
 * The journal deadline in Settings only warns; admin moves late papers by hand ("not selected").
 * Authors see the outcome, never the journal's reason. Papers in the conference track are hidden from the journal.
 */
class JournalController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'max' => 'validation.too_long'];

    /** Tabs of the journal's list, in order. */
    private const TABS = ['waiting', 'selected', 'accepted', 'all'];

    public function index(Request $request): Response
    {
        $filters = $request->only('q', 'tab');
        $tab = in_array($filters['tab'] ?? null, self::TABS, true) ? $filters['tab'] : 'waiting';
        $byTab = fn ($query, string $tab) => match ($tab) {
            'waiting' => $query->where('status', SubmissionStatus::Submitted),
            'selected' => $query->where('status', SubmissionStatus::JournalSelected),
            'accepted' => $query->where('status', SubmissionStatus::JournalAccepted),
            default => $query->journalStage(),
        };

        $papers = $byTab(Submission::currentYear(), $tab)
            ->with('track', 'authors')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('title_th', 'like', "%{$q}%")
                ->orWhere('title_en', 'like', "%{$q}%")
                ->orWhereHas('authors', fn ($query) => $query->where('name', 'like', "%{$q}%"))))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Submission $submission) => [
                ...$submission->toClient(),
                'pastDeadline' => $submission->isPastJournalDeadline(),
            ]);

        return Inertia::render('Admin/Journal/Index', [
            'papers' => $papers,
            'filters' => [...$filters, 'tab' => $tab],
            'counts' => collect(self::TABS)->mapWithKeys(fn (string $key) => [$key => $byTab(Submission::currentYear(), $key)->count()]),
            'pastDeadline' => Submission::currentYear()->where('status', SubmissionStatus::Submitted)->get()->filter->isPastJournalDeadline()->count(),
            'journalDeadline' => Submission::journalDeadline()?->toIso8601String(),
            ...Submission::window(),
        ]);
    }

    public function show(Request $request, Submission $submission): Response
    {
        abort_unless($submission->isJournalStage(), 404);
        $submission->load('track', 'authors', 'user');

        return Inertia::render('Admin/Journal/Show', [
            'submission' => $submission->toClient(full: true),
            'versions' => $submission->versionsFor('author'),
            'files' => $submission->currentFiles(),
            'journal' => self::panel($request->user(), $submission),
            // Paper history without reviewer activity or internal notes (there are none at this stage anyway)
            'history' => $submission->events()->with('user')->get()
                ->filter(fn (SubmissionEvent $event) => $event->action->visibleToAuthor())
                ->map->toClient(withActor: true)
                ->values(),
            ...Submission::window(),
        ]);
    }

    /** Step 1: the journal's selection (or admin moving a paper to the conference track). */
    public function select(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeSelectedByJournal(), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['select', 'decline'])],
            'comment' => ['nullable', 'string', 'max:5000'],
        ], self::MESSAGES);

        $from = $submission->status;
        $submission->status = $data['decision'] === 'select' ? SubmissionStatus::JournalSelected : SubmissionStatus::Screening;
        $submission->save();
        $submission->log(SubmissionAction::JournalScreened, $request->user(), $from, $data['comment'] ?? null);

        return back()->with('success', $data['decision'] === 'select' ? 'journal.toast.selected' : 'journal.toast.declined');
    }

    /** Step 2: the journal's final answer for a selected paper. */
    public function result(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->status === SubmissionStatus::JournalSelected, 403);

        $data = $request->validate([
            'result' => ['required', Rule::in(['accepted', 'rejected'])],
            'comment' => ['nullable', 'string', 'max:5000'],
        ], self::MESSAGES);

        $from = $submission->status;
        $submission->status = $data['result'] === 'accepted' ? SubmissionStatus::JournalAccepted : SubmissionStatus::Screening;
        $submission->save();
        $submission->log(SubmissionAction::JournalResult, $request->user(), $from, $data['comment'] ?? null);

        return back()->with('success', $data['result'] === 'accepted' ? 'journal.toast.accepted' : 'journal.toast.declined');
    }

    /**
     * What the journal panel shows on a paper page (journal page, and the editors' page where only admin may act):
     * the journal's decisions so far with their reasons (staff only), and which step is open.
     */
    public static function panel(User $user, Submission $submission): array
    {
        $canAct = $user->moduleAccess('journal') === 'full';

        return [
            'canAct' => $canAct,
            'canSelect' => $canAct && $submission->canBeSelectedByJournal(),
            'canResult' => $canAct && $submission->status === SubmissionStatus::JournalSelected,
            'deadline' => Submission::journalDeadline()?->toIso8601String(),
            'pastDeadline' => $submission->isPastJournalDeadline(),
            'url' => Setting::get('journal_url'),
            'decisions' => $submission->events()->with('user')
                ->whereIn('action', [SubmissionAction::JournalScreened, SubmissionAction::JournalResult])
                ->get()->map->toClient(withActor: true)->values(),
        ];
    }
}
