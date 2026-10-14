<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin "view as this user" (read only) and the way back. See App\Support\Impersonation. */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        if ($refusal = Impersonation::refusal($request->user(), $user)) {
            return back()->with('error', $refusal);
        }

        Impersonation::start($request, $user);

        // Land where that user lands after signing in
        return redirect($user->canAccessBackend() ? '/admin' : '/');
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless(Impersonation::active(), 404);

        $returnTo = Impersonation::stop($request);

        return $returnTo
            ? redirect($returnTo)->with('success', 'impersonation.stopped')
            : redirect()->route('home');
    }
}
