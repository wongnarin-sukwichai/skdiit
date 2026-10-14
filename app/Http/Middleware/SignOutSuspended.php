<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account suspended while signed in is signed out on its next request.
 */
class SignOutSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        // An admin viewing as this user keeps their own session (GuardImpersonation ends it)
        if ($request->user()?->suspended_at && ! Impersonation::active()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'auth.suspended']);
        }

        return $next($request);
    }
}
