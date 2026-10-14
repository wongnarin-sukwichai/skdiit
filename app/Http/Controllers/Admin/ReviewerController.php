<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reviewers: their accounts and the tracks each one covers. Admin manages them; editors can view.
 * When a reviewer is assigned to a paper, only reviewers of that paper's track are listed.
 */
class ReviewerController extends Controller
{
    use ManagesAccounts;

    protected function roles(): array
    {
        return ['reviewer'];
    }

    public function index(Request $request): Response
    {
        $track = $request->integer('track') ?: null;

        $accounts = $this->accountList($request, fn ($query) => $query
            ->when($track, fn ($query) => $query->whereHas('tracks', fn ($query) => $query->whereKey($track)))
            ->with('tracks:id'));

        return Inertia::render('Admin/Reviewers', [
            'accounts' => $accounts->through(fn (User $user) => [
                ...$this->accountRow($user),
                'tracks' => $user->tracks->pluck('id'),
            ]),
            'filters' => $request->only('q', 'track'),
            'tracks' => Track::ordered()->get(['id', 'name_en', 'name_th']),
            // Every reviewer, for the "Reviewer tracks" tab
            'reviewers' => User::where('role', 'reviewer')->orderBy('name')->with('tracks:id')->get()
                ->map(fn (User $user) => [
                    ...$user->only('id', 'name', 'email'),
                    'tracks' => $user->tracks->pluck('id'),
                ]),
        ]);
    }

    public function updateTracks(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);

        $data = $request->validate([
            'tracks' => ['array'],
            'tracks.*' => ['integer', 'exists:tracks,id'],
        ], self::ACCOUNT_MESSAGES);

        $user->tracks()->sync($data['tracks'] ?? []);

        return back()->with('success', 'admin.accounts.saved');
    }
}
