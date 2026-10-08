<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the request only if the user's role can use the module (config/modules.php).
 * Usage: ->middleware('module:registrants'), or 'module' alone to read the {section} route parameter.
 */
class EnsureModule
{
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $user = $request->user();
        $module ??= $request->route('section');

        if (! $user?->moduleAccess($module)) {
            // Staff without this module go back to their dashboard; everyone else to the public site
            return $user?->canAccessBackend() && $module !== 'dashboard'
                ? redirect('/admin')
                : redirect()->route('home');
        }

        return $next($request);
    }
}
