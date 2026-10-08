<?php

/*
|--------------------------------------------------------------------------
| Demo accounts (testing phase only)
|--------------------------------------------------------------------------
|
| One account per role. Seeded by DatabaseSeeder and listed in the login modal
| so testers can sign in quickly. Set DEMO_ACCOUNTS=false (or remove this file
| and its uses) before going live.
|
*/

return [
    'enabled' => (bool) env('DEMO_ACCOUNTS', env('APP_ENV') === 'local'),

    'password' => '1234',

    // email => [name, role, participation]
    'accounts' => [
        'editor@skdiit.test' => ['Demo Editor', 'editor', 'attend'],
        'reviewer@skdiit.test' => ['Demo Reviewer', 'reviewer', 'attend'],
        'finance@skdiit.test' => ['Demo Finance', 'finance', 'attend'],
        'author@skdiit.test' => ['Demo Author', 'user', 'attend_submit'],
        'attendee@skdiit.test' => ['Demo Attendee', 'user', 'attend'],
    ],
];
