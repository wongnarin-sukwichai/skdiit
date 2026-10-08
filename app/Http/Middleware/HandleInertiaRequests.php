<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'name', 'email', 'role', 'affiliation', 'participation'),
                    'accessRole' => $user->accessRole(),
                ] : null,
                // Backend modules the user can open (empty for attendees and guests)
                'modules' => $user ? $user->modules() : [],
            ],
            // Testing phase: demo logins shown in the login modal (config/demo.php)
            'demoAccounts' => fn () => $user || ! config('demo.enabled') ? [] : collect(config('demo.accounts'))
                ->map(fn (array $account, string $email) => [
                    'email' => $email,
                    'password' => config('demo.password'),
                    'role' => (new User(['role' => $account[1], 'participation' => $account[2]]))->accessRole(),
                ])
                ->values()
                ->all(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
