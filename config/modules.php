<?php

/*
|--------------------------------------------------------------------------
| Backend modules and who can use them
|--------------------------------------------------------------------------
|
| One map drives both the sidebar and the route checks (EnsureModule).
| The order here is the order of the sidebar.
|
| Roles (User::accessRole()):
|   admin, editor, reviewer, finance  - staff accounts created by admin
|   author    - registrant who chose "attend + submit"
|   attendee  - registrant who chose "attend" only (no backend access)
|
| Access levels:
|   full  - can use everything in the module
|   view  - read only
|   own   - only their own records, or the ones assigned to them
|
*/

return [
    'dashboard' => [
        'href' => '/admin',
        'icon' => 'house',
        'access' => ['admin' => 'full', 'author' => 'own', 'editor' => 'full', 'reviewer' => 'own', 'finance' => 'own'],
    ],
    'registrants' => [
        'href' => '/admin/registrants',
        'icon' => 'users',
        'access' => ['admin' => 'full', 'editor' => 'view', 'finance' => 'view'],
    ],
    'my-submissions' => [
        'href' => '/admin/my-submissions',
        'icon' => 'file-arrow-up',
        'access' => ['author' => 'own'],
    ],
    'submissions' => [
        'href' => '/admin/submissions',
        'icon' => 'file-lines',
        'access' => ['admin' => 'full', 'editor' => 'full'],
    ],
    'reviews' => [
        'href' => '/admin/reviews',
        'icon' => 'clipboard-check',
        'access' => ['reviewer' => 'own'],
    ],
    'payments' => [
        'href' => '/admin/payments',
        'icon' => 'receipt',
        'access' => ['admin' => 'full', 'finance' => 'full'],
    ],
    'articles' => [
        'href' => '/admin/articles',
        'icon' => 'database',
        'access' => ['admin' => 'full', 'editor' => 'view'],
    ],
    'schedule' => [
        'href' => '/admin/schedule',
        'icon' => 'calendar-days',
        'access' => ['admin' => 'full'],
    ],
    'notifications' => [
        'href' => '/admin/notifications',
        'icon' => 'bell',
        'access' => ['admin' => 'own', 'author' => 'own', 'editor' => 'own', 'reviewer' => 'own', 'finance' => 'own'],
    ],
    'reports' => [
        'href' => '/admin/reports',
        'icon' => 'chart-column',
        'access' => ['admin' => 'full', 'editor' => 'full', 'finance' => 'own'],
    ],
    'settings' => [
        'href' => '/admin/settings',
        'icon' => 'gear',
        'access' => ['admin' => 'full'],
    ],
];
