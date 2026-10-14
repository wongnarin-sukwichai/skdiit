<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tracks (กลุ่มสาขา), admin only: their names in both languages.
 * Authors pick a track when submitting; editors can move a paper to another track.
 */
class TrackController extends Controller
{
    public function index(): Response
    {
        $reviewers = User::where('role', 'reviewer')->orderBy('name')->with('tracks:id')->get()
            ->map(fn (User $user) => [
                ...$user->only('id', 'name', 'email', 'academic_title_id'),
                'suspended' => (bool) $user->suspended_at,
                'tracks' => $user->tracks->pluck('id'),
            ]);

        return Inertia::render('Admin/Tracks', [
            'tracks' => Track::ordered()->withCount('reviewers')->get()->map(fn (Track $track) => [
                ...$track->only('id', 'name_en', 'name_th'),
                'reviewers' => $track->reviewers_count,
            ]),
            // Every reviewer with their track ids, for the board (grouped on the client)
            'reviewers' => $reviewers,
        ]);
    }

    /** The reviewers who cover this track (board modal and ✕). Other tracks of each reviewer are untouched. */
    public function updateReviewers(Request $request, Track $track): RedirectResponse
    {
        $data = $request->validate([
            'reviewers' => ['array'],
            'reviewers.*' => ['integer', Rule::exists('users', 'id')->where('role', 'reviewer')],
        ]);

        $track->reviewers()->sync($data['reviewers'] ?? []);

        return back()->with('success', 'admin.accounts.saved');
    }

    public function update(Request $request, Track $track): RedirectResponse
    {
        $track->update($request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_th' => ['required', 'string', 'max:255'],
        ], ['required' => 'validation.required', 'max' => 'validation.too_long']));

        return back()->with('success', 'admin.accounts.saved');
    }
}
