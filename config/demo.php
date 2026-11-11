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

    // email => [name, role, participation]; staff only, one per role (2026-11-11: authors and attendees register themselves for testing)
    'accounts' => [
        'admin@skdiit.test' => ['Demo Admin', 'admin', 'attend'],
        'editor@skdiit.test' => ['Demo Editor', 'editor', 'attend'],
        'reviewer@skdiit.test' => ['Demo Reviewer', 'reviewer', 'attend'],
        'journal@skdiit.test' => ['Demo Journal', 'journal', 'attend'],
    ],

    // Email to test accounts (this domain) goes to this address instead, the subject saying who it was for
    // (App\Listeners\RedirectTestMail). Empty = send nothing to the test domain's addresses at all.
    'mail_domain' => 'skdiit.test',
    'mail_to' => env('DEMO_MAIL_TO', 'wongnarin.s@msu.ac.th'),
];
