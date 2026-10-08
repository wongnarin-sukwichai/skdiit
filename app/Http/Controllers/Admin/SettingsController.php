<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings (admin only): users and roles, tracks, reviewer tracks, submission rules (deadline, file upload limit).
 */
class SettingsController extends Controller
{
    /** Validation messages are translation keys; the Vue client renders them in the active language. */
    private const MESSAGES = [
        'required' => 'validation.required',
        'email' => 'validation.email',
        'email.unique' => 'validation.email_taken',
        'password.min' => 'validation.password_min',
        'integer' => 'validation.integer',
        'max_mb.min' => 'validation.upload_range',
        'max_mb.max' => 'validation.upload_range',
        'deadline.date_format' => 'validation.date',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->only('q', 'role');

        $users = User::query()
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
            ))
            ->when($filters['role'] ?? null, fn ($query, string $role) => match ($role) {
                'author' => $query->where('role', 'user')->where('participation', 'attend_submit'),
                'attendee' => $query->where('role', 'user')->where('participation', '!=', 'attend_submit'),
                default => $query->where('role', $role),
            })
            ->orderByRaw("FIELD(role, 'admin', 'editor', 'reviewer', 'finance', 'user')") // staff first, by role
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                ...$user->only('id', 'name', 'email', 'role', 'affiliation'),
                'accessRole' => $user->accessRole(),
                'isStaff' => in_array($user->role, User::STAFF_ROLES, true),
                'createdAt' => $user->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/Settings', [
            'users' => $users,
            'filters' => $filters,
            'staffRoles' => User::STAFF_ROLES,
            'tracks' => Track::ordered()->withCount('reviewers')->get()->map(fn (Track $track) => [
                ...$track->only('id', 'name_en', 'name_th'),
                'reviewers' => $track->reviewers_count,
            ]),
            'reviewers' => User::where('role', 'reviewer')->orderBy('name')->with('tracks:id')->get()
                ->map(fn (User $user) => [
                    ...$user->only('id', 'name', 'email'),
                    'tracks' => $user->tracks->pluck('id'),
                ]),
            'submission' => [
                'maxMb' => (int) Setting::get('upload_max_mb'),
                'serverMaxMb' => Setting::serverUploadLimitMb(),
                'deadline' => Setting::get('submission_deadline'),
            ],
        ]);
    }

    /** Admin creates staff accounts only; registrants sign up themselves. */
    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'password' => ['required', 'string', 'min:6'],
        ], self::MESSAGES);

        User::create($data);

        return back()->with('success', 'admin.settings.users.created');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $isStaff = in_array($user->role, User::STAFF_ROLES, true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Registrants keep role 'user'; switching attend-only to author is a separate flow (to be designed)
            'role' => $isStaff ? ['required', Rule::in(User::STAFF_ROLES)] : ['prohibited'],
            'password' => ['nullable', 'string', 'min:6'],
        ], self::MESSAGES);

        if ($user->is($request->user()) && ($data['role'] ?? 'admin') !== 'admin') {
            throw ValidationException::withMessages(['role' => 'validation.own_role']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        // A reviewer moved to another role no longer belongs to any track
        if ($user->role !== 'reviewer') {
            $user->tracks()->detach();
        }

        return back()->with('success', 'admin.settings.saved');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);

        $user->delete();

        return back()->with('success', 'admin.settings.users.deleted');
    }

    public function updateTrack(Request $request, Track $track): RedirectResponse
    {
        $track->update($request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_th' => ['required', 'string', 'max:255'],
        ], self::MESSAGES));

        return back()->with('success', 'admin.settings.saved');
    }

    public function updateReviewerTracks(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'reviewer', 404);

        $data = $request->validate([
            'tracks' => ['array'],
            'tracks.*' => ['integer', 'exists:tracks,id'],
        ], self::MESSAGES);

        $user->tracks()->sync($data['tracks'] ?? []);

        return back()->with('success', 'admin.settings.saved');
    }

    public function updateSubmission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_mb' => ['required', 'integer', 'min:1', 'max:'.Setting::serverUploadLimitMb()],
            // Authors can submit and edit until this moment; empty = no deadline
            'deadline' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ], self::MESSAGES);

        Setting::put('upload_max_mb', $data['max_mb']);
        Setting::put('submission_deadline', $data['deadline'] ? str_replace('T', ' ', $data['deadline']) : null);

        return back()->with('success', 'admin.settings.saved');
    }
}
