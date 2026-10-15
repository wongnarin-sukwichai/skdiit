<?php

/*
|--------------------------------------------------------------------------
| Backend modules and who can use them
|--------------------------------------------------------------------------
|
| One map drives both the sidebar and the route checks (EnsureModule).
| The order here is the order of the sidebar; `group` puts a heading above
| each run of modules (admin.menuGroup.* in the i18n files).
|
| Roles (User::accessRole()):
|   admin, editor, reviewer, journal  - staff accounts created by admin
|   author    - registrant who chose "attend + submit"
|   attendee  - registrant who chose "attend" only (no backend access,
|               so never list it here; they use "My details" on the public site)
|
| Access levels:
|   full  - can use everything in the module
|   view  - read only
|   own   - only their own records, or the ones assigned to them
|
| Modules without a page yet render Admin/Placeholder (see routes/web.php).
|
*/

return [
    // Overview
    'dashboard' => [
        'group' => 'overview',
        'href' => '/admin',
        'icon' => 'house',
        'access' => ['admin' => 'full', 'author' => 'own', 'editor' => 'full', 'reviewer' => 'own', 'journal' => 'own'],
    ],
    'notifications' => [
        'group' => 'overview',
        'href' => '/admin/notifications',
        'icon' => 'bell',
        // Admin and editors keep the menu (it has the email log); everyone else reads theirs on the home page
        'access' => ['admin' => 'own', 'editor' => 'own'],
    ],
    // Email a group of people, and the texts of the workflow emails
    'announcements' => [
        'group' => 'overview',
        'href' => '/admin/announcements',
        'icon' => 'bullhorn',
        'access' => ['admin' => 'full', 'editor' => 'full'],
    ],
    'reports' => [
        'group' => 'overview',
        'href' => '/admin/reports',
        'icon' => 'chart-column',
        'access' => ['admin' => 'full', 'editor' => 'full'],
    ],

    // Papers
    'my-submissions' => [
        'group' => 'papers',
        'href' => '/admin/my-submissions',
        'icon' => 'file-arrow-up',
        'access' => ['author' => 'own'],
    ],
    'submissions' => [
        'group' => 'papers',
        'href' => '/admin/submissions',
        'icon' => 'file-lines',
        'access' => ['admin' => 'full', 'editor' => 'full'],
    ],
    // The journal's first gate (admin may do every journal action; editors see it on each paper page)
    'journal' => [
        'group' => 'papers',
        'href' => '/admin/journal',
        'icon' => 'newspaper',
        'access' => ['admin' => 'full', 'journal' => 'full'],
    ],
    'reviews' => [
        'group' => 'papers',
        'href' => '/admin/reviews',
        'icon' => 'clipboard-check',
        'access' => ['reviewer' => 'own'],
    ],
    'publication' => [
        'group' => 'papers',
        'href' => '/admin/publication',
        'icon' => 'book',
        'access' => ['admin' => 'full', 'editor' => 'full'],
    ],

    // Conference event
    'presentations' => [
        'group' => 'event',
        'href' => '/admin/presentations',
        'icon' => 'person-chalkboard',
        'access' => ['admin' => 'full', 'editor' => 'view'],
    ],

    // People (registrants, reviewers and the tracks reviewers cover)
    'registrants' => [
        'group' => 'people',
        'href' => '/admin/registrants',
        'icon' => 'users',
        'access' => ['admin' => 'full', 'editor' => 'view'],
    ],
    'reviewers' => [
        'group' => 'people',
        'href' => '/admin/reviewers',
        'icon' => 'user-check',
        'access' => ['admin' => 'full', 'editor' => 'view'],
    ],
    'tracks' => [
        'group' => 'people',
        'href' => '/admin/tracks',
        'icon' => 'layer-group',
        'access' => ['admin' => 'full', 'editor' => 'view'],
    ],

    // Archive (admin only): finished papers of every conference year
    'articles' => [
        'group' => 'archive',
        'href' => '/admin/articles',
        'icon' => 'database',
        'access' => ['admin' => 'full'],
    ],

    // System
    'staff' => [
        'group' => 'system',
        'href' => '/admin/staff',
        'icon' => 'user-tie',
        'access' => ['admin' => 'full'],
    ],
    'activity-log' => [
        'group' => 'system',
        'href' => '/admin/activity-log',
        'icon' => 'clock-rotate-left',
        // Editors coordinate authors and reviewers: they see paper/review activity and new registrations
        'access' => ['admin' => 'full', 'editor' => 'view'],
    ],
    'settings' => [
        'group' => 'system',
        'href' => '/admin/settings',
        'icon' => 'gear',
        'access' => ['admin' => 'full'],
    ],
    'account' => [
        'group' => 'system',
        'href' => '/admin/account',
        'icon' => 'user-pen',
        'access' => ['admin' => 'own', 'author' => 'own', 'editor' => 'own', 'reviewer' => 'own', 'journal' => 'own'],
    ],
];
