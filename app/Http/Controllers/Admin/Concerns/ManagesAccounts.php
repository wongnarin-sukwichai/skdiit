<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Account list and actions shared by the Staff (admin, editor, finance) and Reviewers pages.
 * Each controller manages only the roles returned by roles(); other accounts give 404.
 */
trait ManagesAccounts
{
    /** Validation messages are translation keys; the Vue client renders them in the active language. */
    protected const ACCOUNT_MESSAGES = [
        'required' => 'validation.required',
        'email' => 'validation.email',
        'email.unique' => 'validation.email_taken',
        'password.min' => 'validation.password_min',
        'max' => 'validation.too_long',
    ];

    /** @return list<string> */
    abstract protected function roles(): array;

    /** Accounts of these roles, filtered by name/email search and (when there are several roles) role. */
    protected function accountList(Request $request, ?callable $scope = null): LengthAwarePaginator
    {
        $q = $request->query('q');
        $role = in_array($request->query('role'), $this->roles(), true) ? $request->query('role') : null;

        return User::whereIn('role', $role ? [$role] : $this->roles())
            ->when($q, fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
            ))
            ->when($scope, $scope)
            ->orderByRaw("FIELD(role, '".implode("','", $this->roles())."')")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    protected function accountRow(User $user): array
    {
        return [
            ...$user->only('id', 'name', 'email', 'role', 'academic_title_id', 'phone'),
            'emailVerified' => (bool) $user->email_verified_at,
            'suspended' => (bool) $user->suspended_at,
            'createdAt' => $user->created_at?->toDateString(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->accountRules(),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ], self::ACCOUNT_MESSAGES);

        User::create([...$data, 'role' => $data['role'] ?? $this->roles()[0]])
            ->forceFill(['password_by_admin' => true])->save();

        return back()->with('success', 'admin.accounts.created');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);

        $data = $request->validate([
            ...$this->accountRules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
        ], self::ACCOUNT_MESSAGES);

        if ($user->is($request->user()) && ($data['role'] ?? $user->role) !== 'admin') {
            throw ValidationException::withMessages(['role' => 'validation.own_role']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->fill($data);
        if (isset($data['password'])) {
            $user->password_by_admin = true;
        }
        $user->save();

        return back()->with('success', 'admin.accounts.saved');
    }

    /** Only suspended accounts can be deleted, so nobody is removed by a single click. */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        abort_if($user->is($request->user()) || ! $user->suspended_at, 403);

        $user->delete();

        return back()->with('success', 'admin.accounts.deleted');
    }

    /** A new password sent to the account's email; admin does not see it. */
    public function sendPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        abort_if($user->is($request->user()), 403);

        $user->emailNewPassword();

        return back()->with('success', 'admin.accounts.passwordSent');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        abort_if($user->is($request->user()), 403);

        $user->forceFill(['suspended_at' => now()])->save();

        return back()->with('success', 'admin.accounts.suspendedNotice');
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $this->ensureManaged($user);

        $user->forceFill(['suspended_at' => null])->save();

        return back()->with('success', 'admin.accounts.unsuspendedNotice');
    }

    /** "Email didn't arrive": a password shown to admin once, to pass on by private chat. */
    public function password(Request $request, User $user): RedirectResponse
    {
        $this->ensureManaged($user);
        abort_if($user->is($request->user()), 403);

        return back()->with('generatedPassword', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->issuePasswordByAdmin($request->user()),
        ]);
    }

    protected function ensureManaged(User $user): void
    {
        abort_unless(in_array($user->role, $this->roles(), true), 404);
    }

    /** Fields shared by create and update; the role picker only exists when there is more than one role. */
    private function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'academic_title_id' => ['nullable', 'integer', 'exists:academic_titles,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            ...(count($this->roles()) > 1 ? ['role' => ['required', Rule::in($this->roles())]] : []),
        ];
    }
}
