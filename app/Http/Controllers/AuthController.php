<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Validation messages are translation keys; the Vue client renders them in the active language.
     */
    private const MESSAGES = [
        'required' => 'validation.required',
        'email' => 'validation.email',
        'email.unique' => 'validation.email_taken',
        'password.min' => 'validation.password_min',
        'password.confirmed' => 'validation.password_confirmed',
        'participation.in' => 'validation.required',
    ];

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], self::MESSAGES);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'auth.failed',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return $user->canAccessBackend()
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->route('home');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'affiliation' => ['required', 'string', 'max:100'],
            'participation' => ['required', Rule::in(['attend', 'attend_submit'])],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], self::MESSAGES);

        $user = User::create([...$data, 'role' => 'user']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'auth.registered');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
