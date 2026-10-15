<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My account" (every backend role, own record only): name, academic title, phone, affiliation (registrants)
 * and password. The email is the sign-in name and the papers' contact, so only admin can change it.
 * The password itself changes through AuthController::changePassword (shared with "My details").
 * update() also serves "My details" on the public site (PUT /my-details), so attendees can fix their own details.
 */
class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Admin/Account', [
            'account' => [
                ...$user->only('name', 'email', 'academic_title_id', 'phone', 'affiliation', 'affiliation_other', 'participation'),
                'role' => $user->accessRole(),
                'isRegistrant' => $user->role === 'user',
                'emailVerified' => (bool) $user->email_verified_at,
                'createdAt' => $user->created_at?->toDateString(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_title_id' => ['nullable', 'integer', 'exists:academic_titles,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            // Registrants chose an affiliation at sign-up; staff accounts have none
            'affiliation' => $user->role === 'user' ? ['required', Rule::in(Affiliation::codes())] : ['prohibited'],
            'affiliation_other' => $user->role === 'user' ? ['nullable', 'required_if:affiliation,'.Affiliation::OTHER, 'string', 'max:255'] : ['prohibited'],
        ], [
            'required' => 'validation.required',
            'required_if' => 'validation.required',
            'affiliation.in' => 'validation.required',
            'max' => 'validation.too_long',
        ]);

        // The typed name only counts with "Other"; picking a list entry clears it
        if (isset($data['affiliation'])) {
            $data['affiliation_other'] = $data['affiliation'] === Affiliation::OTHER ? trim($data['affiliation_other']) : null;
        }

        $user->update($data);

        return back()->with('success', 'admin.account.saved');
    }
}
