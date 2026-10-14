<?php

namespace App\Http\Controllers;

use App\Mail\LoginPassword;
use App\Models\Affiliation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

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

            throw ValidationException::withMessages([
                'email' => 'auth.failed',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Suspended accounts cannot sign in (checked after the password, so it reveals nothing to guessers)
        if ($user->suspended_at) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'auth.suspended']);
        }

        $request->session()->regenerate();

        RateLimiter::clear($throttleKey);

        // Signing in with the emailed password proves the email works (not a password admin passed on)
        if (! $user->email_verified_at && ! $user->password_by_admin) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user->canAccessBackend()
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->route('home');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'affiliation' => ['required', Rule::in(Affiliation::codes())],
            'affiliation_other' => ['nullable', 'required_if:affiliation,'.Affiliation::OTHER, 'string', 'max:255'],
            // Optional: lets the editors (who coordinate) call when email does not get through
            'phone' => ['nullable', 'string', 'max:30'],
            'participation' => ['required', Rule::in(['attend', 'attend_submit'])],
        ], self::MESSAGES);

        // The password goes to the email address; the registrant signs in with it
        $password = User::generatePassword();
        $user = User::create([
            ...$data,
            'affiliation_other' => $data['affiliation'] === Affiliation::OTHER ? trim($data['affiliation_other']) : null,
            'password' => $password,
            'role' => 'user',
        ]);
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

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function mailPassword(User $user, string $password, string $kind): void
    {
        try {
            Mail::to($user)->send(new LoginPassword($user, $password, $kind));
        } catch (Throwable $e) {
            // The account is kept; the registrant can ask for a new password from the login modal
            report($e);
        }
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
