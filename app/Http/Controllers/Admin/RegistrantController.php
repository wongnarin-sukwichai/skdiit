<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Mail\LoginPassword;
use App\Models\Affiliation;
use App\Models\AuthorRequest;
use App\Models\MealOption;
use App\Models\Submission;
use App\Models\User;
use App\Support\WorkflowMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Registrants of the current conference year (role 'user'): admin manages them, editors can view.
 * Registrants are never deleted, only suspended. Attend-only registrants may ask to submit papers
 * (AuthorRequest); admin approves or declines on the "Submit requests" tab.
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
            ->latest()
            ->get();
        $pending = AuthorRequest::pending()->where('year', $year)->pluck('user_id')->flip();

        return Inertia::render('Admin/Registrants', [
            'registrants' => $registrants->map(fn (User $user) => [
                ...$user->only('id', 'name', 'email', 'phone', 'affiliation', 'affiliation_other', 'participation', ...User::MEAL_FIELDS),
                'paper' => ($paper = $user->submissions->first()) ? [
                    'id' => $paper->id,
                    'code' => $paper->code,
                    'title' => ['en' => $paper->title_en, 'th' => $paper->title_th],
                ] : null,
                'paperStatus' => $user->submissions->first()?->status->value,
                'suspended' => (bool) $user->suspended_at,
                'verified' => (bool) $user->email_verified_at,
                'registeredAt' => $user->created_at?->toIso8601String(),
                'requestPending' => $pending->has($user->id),
            ]),
            // Pending first, then the latest decisions of this year
            'requests' => AuthorRequest::with('user', 'decider')->where('year', $year)
                ->orderByRaw("status = 'pending' desc")->latest('id')->limit(200)->get()->map->toClient(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // Same as signing up: a 6-digit password goes to the email address
        $password = User::generatePassword();
        $user = User::create([...$data, 'password' => $password, 'role' => 'user']);
        $user->forceFill(['registered_year' => Submission::conferenceYear()])->save();

        try {
            Mail::to($user)->send(new LoginPassword($user, $password, 'registered'));
        } catch (Throwable $e) {
            // The account is kept; admin can send a new password from the ⋯ menu
            report($e);
        }

        return back()->with('success', 'admin.registrants.toast.created');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        $data = $this->validated($request, $user);

        // Back to attend only is allowed while there is no paper
        if ($data['participation'] === 'attend' && $user->participation === 'attend_submit' && $user->submissions()->exists()) {
            throw ValidationException::withMessages(['participation' => 'admin.registrants.form.participationHasPaper']);
        }

        $becameAuthor = $data['participation'] === 'attend_submit' && $user->participation === 'attend';
        $user->update($data);

        if ($becameAuthor) {
            $this->grantAuthor($user, $request->user());
        }

        return back()->with('success', $becameAuthor ? 'admin.registrants.toast.participationChanged' : 'admin.registrants.toast.updated');
    }

    /** One role per account: a registrant without papers becomes a reviewer (and moves to the Reviewers page). */
    public function makeReviewer(User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        abort_if($user->submissions()->exists(), 422);

        $user->forceFill(['role' => 'reviewer'])->save();
        AuthorRequest::pending()->where('user_id', $user->id)->delete();

        return back()->with('success', 'admin.registrants.toast.madeReviewer');
    }

    public function approve(Request $request, AuthorRequest $authorRequest): RedirectResponse
    {
        abort_unless($authorRequest->status === 'pending', 409);
        $user = $authorRequest->user;

        $user->update(['participation' => 'attend_submit']);
        $this->grantAuthor($user, $request->user());

        return back()->with('success', 'admin.registrants.requests.approved');
    }

    public function reject(Request $request, AuthorRequest $authorRequest): RedirectResponse
    {
        abort_unless($authorRequest->status === 'pending', 409);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']], ['max' => 'validation.too_long']);

        $authorRequest->update(['status' => 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'note' => $data['note'] ?? null]);
        WorkflowMail::account('author_request_rejected', $authorRequest->user, '/', ['comment' => $data['note'] ?? '']);

        return back()->with('success', 'admin.registrants.requests.rejected');
    }

    /** Now an author: close a pending request as approved and tell the registrant (they keep their password). */
    private function grantAuthor(User $user, User $admin): void
    {
        AuthorRequest::pending()->where('user_id', $user->id)
            ->update(['status' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now()]);

        WorkflowMail::account('author_request_approved', $user, '/admin/my-submissions');
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
            'participation' => ['required', Rule::in(['attend', 'attend_submit'])],
            ...MealOption::rules(required: false),
        ], [
            ...self::ACCOUNT_MESSAGES,
            'required_if' => 'validation.required',
            'affiliation.in' => 'validation.required',
            'participation.in' => 'validation.required',
            ...MealOption::MESSAGES,
        ]);

        return [
            ...collect($data)->only('name', 'phone', 'affiliation', 'participation')->all(),
            ...MealOption::columns($data),
            'email' => strtolower($data['email']),
            'affiliation_other' => $data['affiliation'] === Affiliation::OTHER ? trim($data['affiliation_other']) : null,
        ];
    }
}
