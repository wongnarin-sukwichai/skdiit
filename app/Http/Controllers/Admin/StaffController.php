<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAccounts;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff accounts (admin only): admin, editor and journal. Reviewers have their own page; registrants sign up themselves.
 */
class StaffController extends Controller
{
    use ManagesAccounts;

    protected function roles(): array
    {
        return ['admin', 'editor', 'journal'];
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Staff', [
            'accounts' => $this->accountList($request)->through(fn (User $user) => $this->accountRow($user)),
            'filters' => $request->only('q', 'role'),
            'roles' => $this->roles(),
        ]);
    }
}
