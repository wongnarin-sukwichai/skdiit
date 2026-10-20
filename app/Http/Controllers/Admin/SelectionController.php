<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\Track;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Selection: the first gate as editors (and admin) see it (2026-10-20). Every paper of the year with where the first
 * selection sent it (Journal / Conference), its files, and the Conference button once the journal deadline has passed
 * (Submission::canBePassedOnBy). The decisions go to JournalController::select; the journal works in its own menu.
 */
class SelectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only('q', 'tab', 'track');
        $tab = in_array($filters['tab'] ?? null, Submission::GATE_TABS, true) ? $filters['tab'] : 'waiting';
        $canAct = $user->moduleAccess('journal') === 'full'; // admin: also selects for the journal and records its answer

        $papers = Submission::currentYear()->gate($tab)
            ->with('track', 'authors')
            ->withGate()
            ->when($filters['track'] ?? null, fn ($query, $track) => $query->where('track_id', $track))
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
                'gate' => $submission->gate(),
                'pastDeadline' => $submission->isPastJournalDeadline(),
                'files' => collect($submission->currentFiles())->only('pdf', 'word', 'turnitin')->filter()->all(),
                'canSelect' => $canAct && $submission->canBeSelectedByJournal(),
                'canDecline' => $submission->canBePassedOnBy($user),
                'canResult' => $canAct && $submission->status === SubmissionStatus::JournalSelected,
            ]);

        return Inertia::render('Admin/Selection/Index', [
            'papers' => $papers,
            'filters' => [...$filters, 'tab' => $tab],
            'counts' => collect(Submission::GATE_TABS)->mapWithKeys(fn (string $key) => [$key => Submission::currentYear()->gate($key)->count()]),
            'tracks' => Track::ordered()->get()->map->toClient(),
            'journalDeadline' => Submission::journalDeadline()?->toIso8601String(),
            'declineBy' => $user->isAdmin() ? 'admin' : 'editor',
            ...Submission::window(),
        ]);
    }
}
