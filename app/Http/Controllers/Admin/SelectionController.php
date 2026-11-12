<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Selection: the first gate (client request 2026-11-11). Admin, editors and the journal role alike see every paper of
 * the year and, after the submission deadline, set its track and either offer it to one partner journal or send it to
 * the conference track. The author answers the offer on their paper page (MySubmissionController::answerJournal);
 * unanswered offers lapse to the conference track (Submission::expireJournalOffers). The list exports to Excel.
 * The journal role has no Submissions menu, so it reads a paper here (show); editors use the Submissions page.
 */
class SelectionController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'required_if' => 'validation.required', 'max' => 'validation.too_long'];

    public function index(Request $request): Response
    {
        Submission::expireJournalOffers();
        $user = $request->user();
        $filters = $request->only('q', 'tab', 'track', 'journal');
        $tab = in_array($filters['tab'] ?? null, Submission::GATE_TABS, true) ? $filters['tab'] : 'waiting';

        $papers = $this->filtered($filters, $tab)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Submission $submission) => [
                ...$submission->toClient(),
                'gate' => $submission->gate(),
                'declinedJournal' => (bool) $submission->declined_journal,
                'pastDeadline' => $submission->isPastJournalDeadline(),
                'files' => collect($submission->currentFiles())->only('pdf', 'word', 'turnitin')->filter()->all(),
                'canRoute' => $submission->canBeRoutedBy($user),
                'canSetTrack' => $submission->trackCanBeSetBy($user),
            ]);

        return Inertia::render('Admin/Selection/Index', [
            'papers' => $papers,
            'filters' => [...$filters, 'tab' => $tab],
            'counts' => collect(Submission::GATE_TABS)->mapWithKeys(fn (string $key) => [$key => Submission::currentYear()->gate($key)->count()]),
            'tracks' => Track::ordered()->get()->map->toClient(),
            'journals' => Journal::ordered()->get()->map(fn (Journal $journal) => [...$journal->toClient(), 'active' => $journal->active]),
            'journalDeadline' => Submission::journalDeadline()?->toIso8601String(),
            'answerDays' => Submission::journalAnswerDays(),
            // Editors and admin open a paper on the Submissions page; the journal role here
            'showUrl' => $user->moduleAccess('submissions') ? '/admin/submissions' : '/admin/selection',
            ...Submission::window(),
        ]);
    }

    /** Offer the paper to a journal, or send it to the conference track; the track is set at the same time. */
    public function route(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeRoutedBy($request->user()), 403);

        $data = $request->validate([
            'to' => ['required', Rule::in(['journal', 'conference'])],
            'track_id' => ['required', 'integer', 'exists:tracks,id'],
            'journal_id' => ['nullable', 'required_if:to,journal', 'integer', Rule::exists('journals', 'id')->where('active', true)],
            'comment' => ['nullable', 'string', 'max:5000'],
        ], self::MESSAGES);

        $journal = $data['to'] === 'journal' ? Journal::find($data['journal_id']) : null;
        $submission->route($request->user(), (int) $data['track_id'], $journal, $data['comment'] ?? null);

        return back()->with('success', $journal ? 'journal.toast.offered' : 'journal.toast.declined');
    }

    /** Editors and admin put the paper in a track from the Selection list (2026-11-12: authors no longer pick one). */
    public function updateTrack(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->trackCanBeSetBy($request->user()), 403);
        $data = $request->validate(['track_id' => ['required', 'integer', 'exists:tracks,id']], self::MESSAGES);
        $submission->setTrack($request->user(), (int) $data['track_id']);

        return back()->with('success', 'submissions.trackChanged');
    }

    /** The list as an Excel file: every paper of the tab and filters (not only the page shown). */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only('q', 'tab', 'track', 'journal');
        $tab = in_array($filters['tab'] ?? null, Submission::GATE_TABS, true) ? $filters['tab'] : 'all';
        $th = $request->query('lang') !== 'en';
        $papers = $this->filtered($filters, $tab)->with('user')->get();

        $headers = $th
            ? ['รหัส', 'ชื่อบทความ (ไทย)', 'ชื่อบทความ (อังกฤษ)', 'ผู้แต่ง', 'ผู้ส่ง', 'อีเมลผู้ส่ง', 'กลุ่มสาขา', 'สถานะ', 'วารสาร', 'ตอบรับภายใน', 'วันที่ส่ง']
            : ['Code', 'Title (Thai)', 'Title (English)', 'Authors', 'Submitter', 'Submitter email', 'Track', 'Status', 'Journal', 'Answer by', 'Submitted'];

        return response()->streamDownload(function () use ($papers, $headers, $th) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers, (new Style)->setFontBold()));
            foreach ($papers as $paper) {
                $writer->addRow(Row::fromValues([
                    $paper->code, $paper->title_th, $paper->title_en,
                    $paper->authors->pluck('name')->join(', '),
                    $paper->user?->name ?? '', $paper->user?->email ?? '',
                    $th ? $paper->track?->name_th : $paper->track?->name_en,
                    $paper->status->label($th ? 'th' : 'en'),
                    $paper->journal?->name ?? '',
                    $paper->journal_answer_due?->toDateString() ?? '',
                    $paper->created_at->toDateString(),
                ]));
            }
            $writer->close();
        }, 'skdiit-selection-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** A paper as the journal role reads it: details, files and the first-gate panel (no reviews, no internal notes). */
    public function show(Request $request, Submission $submission): Response
    {
        $submission->load('track', 'authors', 'user', 'journal');

        return Inertia::render('Admin/Selection/Show', [
            'submission' => $submission->toClient(full: true),
            'versions' => $submission->versionsFor('author'),
            'files' => $submission->currentFiles(),
            'journal' => self::panel($request->user(), $submission),
            // Paper history without reviewer activity or internal notes
            'history' => $submission->events()->with('user')->get()
                ->filter(fn (SubmissionEvent $event) => $event->action->visibleToAuthor())
                ->map->toClient(withActor: true)
                ->values(),
            ...Submission::window(),
        ]);
    }

    /**
     * The first-gate panel on a paper page (here and on the editors' Submissions page): the decisions and the
     * author's answers so far (with the staff's reasons), and whether this user may route the paper now.
     */
    public static function panel(User $user, Submission $submission): array
    {
        return [
            'canRoute' => $submission->canBeRoutedBy($user),
            'tracks' => Track::ordered()->get()->map->toClient(),
            'journals' => Journal::where('active', true)->ordered()->get()->map->toClient(),
            'answerDays' => Submission::journalAnswerDays(),
            'deadline' => Submission::journalDeadline()?->toIso8601String(),
            'pastDeadline' => $submission->isPastJournalDeadline(),
            'decisions' => $submission->events()->with('user')
                ->whereIn('action', [SubmissionAction::JournalScreened, SubmissionAction::JournalResult, SubmissionAction::JournalOfferAnswered])
                ->get()->map->toClient(withActor: true)->values(),
        ];
    }

    /** Papers of the year for a tab, search, track and journal, in code order. */
    private function filtered(array $filters, string $tab): Builder
    {
        return Submission::currentYear()->gate($tab)
            ->with('track', 'authors', 'journal')
            ->withDeclinedJournal()
            ->when($filters['track'] ?? null, fn ($query, $track) => $query->where('track_id', $track))
            ->when($filters['journal'] ?? null, fn ($query, $journal) => $query->where('journal_id', $journal))
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('title_th', 'like', "%{$q}%")
                ->orWhere('title_en', 'like', "%{$q}%")
                ->orWhereHas('authors', fn ($query) => $query->where('name', 'like', "%{$q}%"))))
            ->orderBy('code');
    }
}
