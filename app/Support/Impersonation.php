<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Admin "view as this user" for support: the admin signs in as another account to see exactly what they see.
 * Read only (GuardImpersonation blocks every write), ends by itself after MINUTES, and every start/stop is logged
 * (laravel.log until the Activity log module exists).
 */
class Impersonation
{
    public const MINUTES = 30;

    private const KEY = 'impersonation';

    /** Why $admin may not view as $target, as a translation key; null when allowed. */
    public static function refusal(User $admin, User $target): ?string
    {
        return match (true) {
            static::active() => 'impersonation.alreadyActive',
            $admin->accessRole() !== 'admin' => 'impersonation.adminOnly',
            $admin->is($target) => 'impersonation.self',
            $target->accessRole() === 'admin' => 'impersonation.otherAdmin',
            (bool) $target->suspended_at => 'impersonation.suspended',
            default => null,
        };
    }

    public static function start(Request $request, User $target): void
    {
        $admin = $request->user();

        $request->session()->put(self::KEY, [
            'by' => $admin->id,
            'started_at' => now()->toIso8601String(),
            'return_to' => url()->previous('/admin'),
        ]);
        Auth::login($target);

        Log::info('impersonation.start', ['admin' => $admin->email, 'user' => $target->email, 'role' => $target->accessRole()]);
    }

    /** Back to the admin account; returns the page the admin started from (null when the admin is gone). */
    public static function stop(Request $request, string $reason = 'stopped'): ?string
    {
        $data = $request->session()->pull(self::KEY);
        $admin = $data ? User::find($data['by']) : null;

        Log::info('impersonation.stop', [
            'admin' => $admin?->email,
            'user' => $request->user()?->email,
            'reason' => $reason,
            'minutes' => $data ? (int) Carbon::parse($data['started_at'])->diffInMinutes(now()) : null,
        ]);

        if (! $admin || $admin->suspended_at) {
            Auth::logout();

            return null;
        }

        Auth::login($admin);

        return $data['return_to'];
    }

    public static function active(): bool
    {
        return session()->has(self::KEY);
    }

    public static function expired(): bool
    {
        $startedAt = session(self::KEY.'.started_at');

        return $startedAt && Carbon::parse($startedAt)->addMinutes(self::MINUTES)->isPast();
    }

    /** Shared with the client (HandleInertiaRequests) for the banner. */
    public static function forClient(?User $user): ?array
    {
        if (! static::active() || ! $user) {
            return null;
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->accessRole(),
            'by' => User::find(session(self::KEY.'.by'))?->name,
            'endsAt' => Carbon::parse(session(self::KEY.'.started_at'))->addMinutes(self::MINUTES)->toIso8601String(),
        ];
    }
}
