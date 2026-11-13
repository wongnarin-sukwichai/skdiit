<?php

namespace App\Http\Middleware;

use App\Models\AcademicTitle;
use App\Models\Affiliation;
use App\Models\MealOption;
use App\Models\Submission;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Support\SiteContent;

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
                    ...$user->only('id', 'name', 'email', 'role', 'affiliation', 'affiliation_other', 'phone', 'participation', 'registered_year', 'created_at', ...User::MEAL_FIELDS),
                    'accessRole' => $user->accessRole(),
                ] : null,
                // Backend modules the user can open (empty for attendees and guests)
                'modules' => $user ? $user->modules() : [],
            ],
            // Admin viewing as this user (banner, read only): { name, email, role, by, endsAt } | null
            'impersonation' => fn () => Impersonation::forClient($user),
            // Conference information and public-site texts admin edits in Settings (useSite())
            'site' => fn () => [...SiteContent::all(), 'year' => Submission::conferenceYear()],
            // Backend, admin and editors: the work along the schedule, shown above every page (EditorTimeline)
            'editorStages' => fn () => \App\Support\EditorStages::forClient($user),
            // Backend: saved timeline rows not enforced yet (useSchedulePlan; the rest of the plan is on the client)
            // Sign-up window (row "registration", the one locked row): { state, start, end } for the sign-up form
            'registration' => fn () => \App\Support\SchedulePlan::registration(),
            // Backend: my notifications (bell, home card, Notifications page): { items (latest 50), unread }
            // Admin: the system is closed to everyone else (banner in the backend)
            'siteClosed' => fn () => $user?->accessRole() === 'admin' && \App\Support\SiteClosure::isClosed(),
            'notifications' => fn () => \App\Models\UserNotification::forClient($user),
            'schedulePlan' => fn () => $user?->modules() ? \App\Support\SchedulePlan::all() : [],
            // Affiliation list for the sign-up form and labels (useAffiliations); 'other' is added on the client
            'affiliations' => fn () => Affiliation::ordered()->get()->map->toClient(),
            // Meal preferences for the sign-up form, "My details" and labels (useMealOptions); 'other' is added on the client
            'mealOptions' => fn () => MealOption::ordered()->get()->map->toClient(),
            // Backend: academic titles for pickers and name prefixes (useAcademicTitles)
            'academicTitles' => fn () => $user?->modules() ? AcademicTitle::ordered()->get()->map->toClient() : [],
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
                'error' => fn () => $request->session()->get('error'),
                // { email, devPassword } after a password was emailed
                'passwordSent' => fn () => $request->session()->get('passwordSent'),
                // { name, email, password } shown once to admin (Settings: generate a password to pass on)
                'generatedPassword' => fn () => $request->session()->get('generatedPassword'),
                // true when an author tried to submit outside the submission period (MySubmissionController)
                'submissionWindow' => fn () => $request->session()->get('submissionWindow'),
            ],
        ];
    }
}
