<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Models\Affiliation;
use App\Models\EmailLog;
use App\Models\MealOption;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registrants of the current conference year (role 'user'): admin manages them, editors can view.
 * Admin may delete a registrant (user request 2026-11-11): the account goes with every paper of theirs and the papers'
 * files; suspending keeps everything. Every registrant is an author (2026-11-13: attend-only and the
 * "became author" email were removed; participation is always attend_submit).
 * Password, suspend and unsuspend come from ManagesAccounts.
 */
class RegistrantController extends Controller
{
    use ManagesAccounts;

    protected function roles(): array
    {
        return ['user'];
    }

    public function index(): Response
    {
        $year = Submission::conferenceYear();

        $registrants = User::where('role', 'user')->where('registered_year', $year)
            ->with(['submissions' => fn ($query) => $query->currentYear()->latest('id')])
            ->withCount('submissions')
            ->latest()
            ->get();

        return Inertia::render('Admin/Registrants', [
            'registrants' => $registrants->map(fn (User $user) => [
                ...$user->only('id', 'name', 'email', 'phone', 'affiliation', 'affiliation_other', 'participation', ...User::MEAL_FIELDS),
                'paper' => ($paper = $user->submissions->first()) ? [
                    'id' => $paper->id,
                    'code' => $paper->code,
                    'title' => ['en' => $paper->title_en, 'th' => $paper->title_th],
                ] : null,
                'paperStatus' => $user->submissions->first()?->status->value,
                // Every paper of the account (all years): deleting the registrant deletes these too
                'papersTotal' => $user->submissions_count,
                'suspended' => (bool) $user->suspended_at,
                'verified' => (bool) $user->email_verified_at,
                'registeredAt' => $user->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // Same as signing up: a 6-digit password goes to the email address
        $password = User::generatePassword();
        $user = User::create([...$data, 'password' => $password, 'role' => 'user']);
        $user->forceFill(['registered_year' => Submission::conferenceYear()])->save();

        // A failed email is logged; the account is kept and admin can send a new password from the ⋯ menu
        EmailLog::password($user, $password, 'registered');

        return back()->with('success', 'admin.registrants.toast.created');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        $user->update($this->validated($request, $user));

        return back()->with('success', 'admin.registrants.toast.updated');
    }

    /** One role per account: a registrant without papers becomes a reviewer (and moves to the Reviewers page). */
    /**
     * Delete a registrant for good (admin): their papers go too (database cascade), so their stored files
     * (every version, camera-ready, slides, reviewers' attachments) are removed first.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        abort_if($user->is($request->user()), 403);

        DB::transaction(function () use ($user) {
            foreach ($user->submissions()->with('files', 'reviews')->get() as $paper) {
                Storage::delete(array_values(array_filter([
                    $paper->file_path, $paper->camera_ready_path, $paper->slides_path,
                    ...$paper->files->pluck('path'), ...$paper->reviews->pluck('attachment_path'),
                ])));
                $paper->delete();
            }
            $user->delete();
        });

        return back()->with('success', 'admin.registrants.toast.deleted');
    }

    public function makeReviewer(User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        abort_if($user->submissions()->exists(), 422);

        $user->forceFill(['role' => 'reviewer'])->save();

        return back()->with('success', 'admin.registrants.toast.madeReviewer');
    }

    /** Same fields and rules as signing up (AuthController::register), without the PDPA tick. */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'affiliation' => ['required', Rule::in(Affiliation::codes())],
            'affiliation_other' => ['nullable', 'required_if:affiliation,'.Affiliation::OTHER, 'string', 'max:255'],
            ...MealOption::rules(required: false),
        ], [
            ...self::ACCOUNT_MESSAGES,
            'required_if' => 'validation.required',
            'affiliation.in' => 'validation.required',
            ...MealOption::MESSAGES,
        ]);

        return [
            ...collect($data)->only('name', 'phone', 'affiliation')->all(),
            ...MealOption::columns($data),
            'email' => strtolower($data['email']),
            'affiliation_other' => $data['affiliation'] === Affiliation::OTHER ? trim($data['affiliation_other']) : null,
            'participation' => 'attend_submit', // every registrant is an author (2026-11-13)
        ];
    }
}
