<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin views as another user: nothing can be saved or sent on that user's behalf,
 * signing out means "back to admin", and the session ends by itself after Impersonation::MINUTES.
 */
class GuardImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Impersonation::active()) {
            return $next($request);
        }

        if (Impersonation::expired()) {
            $returnTo = Impersonation::stop($request, 'expired');

            return $returnTo ? redirect($returnTo)->with('error', 'impersonation.expired') : redirect()->route('home');
        }

        if ($request->routeIs('logout')) {
            $returnTo = Impersonation::stop($request);

            return $returnTo ? redirect($returnTo)->with('success', 'impersonation.stopped') : redirect()->route('home');
        }

        if (! $request->isMethodSafe() && ! $request->routeIs('impersonation.stop')) {
            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['message' => 'impersonation.readOnly'], 403)
                : back()->with('error', 'impersonation.readOnly');
        }

        return $next($request);
    }
}
