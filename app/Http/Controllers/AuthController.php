<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Affiliation;
use App\Models\EmailLog;
use App\Models\MealOption;
use App\Models\Submission;
use App\Models\User;
use App\Support\SchedulePlan;
use App\Support\SiteClosure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Validation messages are translation keys; the Vue client renders them in the active language.
     */
    private const MESSAGES = [
        'required' => 'validation.required',
        'required_if' => 'validation.required',
        'affiliation.in' => 'validation.required',
        'email' => 'validation.email',
        'email.unique' => 'validation.email_taken',
        'password.min' => 'validation.password_min',
        'password.confirmed' => 'validation.password_confirmed',
        'participation.in' => 'validation.required',
        'pdpa.accepted' => 'auth.pdpaRequired',
        'current_password.current_password' => 'validation.current_password',
    ];

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], self::MESSAGES);

        // Generated passwords are only 6 digits, so failed attempts are limited per email + IP
        $throttleKey = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, config('registration.login_attempts'))) {
            throw ValidationException::withMessages(['email' => 'auth.throttle']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            ActivityLog::record('auth', 'login_failed', Str::lower($credentials['email']));

            throw ValidationException::withMessages([
                'email' => 'auth.failed',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // System closed (Settings › ปิดระบบ): only admin may sign in
        if (SiteClosure::isClosed() && $user->accessRole() !== 'admin') {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'auth.siteClosed']);
        }

        // Suspended accounts cannot sign in (checked after the password, so it reveals nothing to guessers)
        if ($user->suspended_at) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'auth.suspended']);
        }

        $request->session()->regenerate();

        RateLimiter::clear($throttleKey);
        ActivityLog::record('auth', 'login', $user->email, null, $user);

        // Signing in with the emailed password proves the email works (not a password admin passed on)
        if (! $user->email_verified_at && ! $user->password_by_admin) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // Welcome toast with the name (a flash may be a key, or { key, params } for FlashToast)
        $welcome = ['key' => 'auth.welcome', 'params' => ['name' => $user->name]];

        return ($user->canAccessBackend()
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->route('home'))->with('success', $welcome);
    }

    public function register(Request $request): RedirectResponse
    {
        // Settings › กำหนดการ, row "registration" (locked outside its dates)
        if (! SchedulePlan::registrationOpen()) {
            throw ValidationException::withMessages(['email' => 'auth.registrationClosed']);
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'affiliation' => ['required', Rule::in(Affiliation::codes())],
            'affiliation_other' => ['nullable', 'required_if:affiliation,'.Affiliation::OTHER, 'string', 'max:255'],
            // Optional: lets the editors (who coordinate) call when email does not get through
            'phone' => ['nullable', 'string', 'max:30'],
            // Authors only (2026-11-13: attend-only removed everywhere)
            'participation' => ['required', Rule::in(['attend_submit'])],
            // Own meal, plus one optional companion (name + meal)
            ...MealOption::rules(),
            // PDPA: consent to the privacy policy (/privacy) is required to register
            'pdpa' => ['accepted'],
        ], [...self::MESSAGES, ...MealOption::MESSAGES]);

        // The password goes to the email address; the registrant signs in with it
        $password = User::generatePassword();
        $user = User::create([
            ...collect($data)->only('email', 'name', 'affiliation', 'phone', 'participation')->all(),
            ...MealOption::columns($data),
            'affiliation_other' => $data['affiliation'] === Affiliation::OTHER ? trim($data['affiliation_other']) : null,
            'password' => $password,
            'role' => 'user',
        ]);
        $user->forceFill(['pdpa_accepted_at' => now(), 'registered_year' => Submission::conferenceYear()])->save();
        ActivityLog::record('account', 'registered', $user->email, null, $user);
        $this->mailPassword($user, $password, 'registered');

        return back()->with('passwordSent', $this->sentFlash($user->email, $password));
    }

    /**
     * "Didn't get it / forgot it": a new 6-digit password for a registrant account.
     * The answer is the same whether or not the email exists, so it cannot be used to look up accounts.
     */
    public function resendPassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']], self::MESSAGES);
        $email = Str::lower($data['email']);

        $throttleKey = 'resend-password:'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            throw ValidationException::withMessages(['email' => 'auth.throttle']);
        }
        RateLimiter::hit($throttleKey, 600);

        // Staff accounts are managed by admin in Settings
        $user = User::where('email', $email)->where('role', 'user')->first();
        $password = null;

        if ($user) {
            $password = $user->emailNewPassword();
            ActivityLog::record('auth', 'password_requested', $user->email, null, $user);
        }

        return back()->with('passwordSent', $this->sentFlash($email, $password));
    }

    /** "My details": registrants replace the emailed password with their own. */
    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], self::MESSAGES);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'auth.passwordChanged');
    }

    /** A registrant of an earlier year joins the current one (banner on the public site). */
    public function joinYear(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'user', 403);
        abort_unless(SchedulePlan::registrationOpen(), 403);
        $user->forceFill(['registered_year' => Submission::conferenceYear()])->save();

        return back();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function mailPassword(User $user, string $password, string $kind): void
    {
        // A failed email is logged; the account is kept and the registrant can ask for a new password
        EmailLog::password($user, $password, $kind);
    }

    /** Shown by the modal: where it went, plus the password itself only while testing without SMTP. */
    private function sentFlash(string $email, ?string $password): array
    {
        return [
            'email' => $email,
            'devPassword' => config('registration.show_dev_password') ? $password : null,
        ];
    }
}
