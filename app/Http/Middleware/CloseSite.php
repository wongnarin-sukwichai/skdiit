<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use App\Support\SiteClosure;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the system is closed (Settings › ปิดระบบ) every request from anyone but admin gets the closed page.
 * Signing in and out stay open, so admin can sign in from that page (AuthController::login turns others away).
 */
class CloseSite
{
    private const OPEN_PATHS = ['login', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! SiteClosure::isClosed()
            || $request->user()?->accessRole() === 'admin'
            || Impersonation::active() // admin viewing as another user
            || ($request->isMethod('POST') && in_array($request->path(), self::OPEN_PATHS, true))) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'closed'], 503);
        }

        return Inertia::render('Closed', ['message' => SiteClosure::message()])->toResponse($request);
    }
}
