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

    // email => [name, role, participation]; seeded by DemoAccountSeeder. One account per staff role, a second reviewer,
    // and five test authors (2026-11-12, for testing selection → journal / conference → review; author@, author_2@,
    // author_3@ and reviewer_2@ also let `sample:journal` / `sample:review` run again)
    'accounts' => [
        'admin@skdiit.test' => ['Demo Admin', 'admin', 'attend'],
        'editor@skdiit.test' => ['Demo Editor', 'editor', 'attend'],
        'reviewer@skdiit.test' => ['Demo Reviewer', 'reviewer', 'attend'],
        'reviewer_2@skdiit.test' => ['Demo Reviewer 2', 'reviewer', 'attend'],
        'journal@skdiit.test' => ['Demo Journal', 'journal', 'attend'],
        'author@skdiit.test' => ['Test Author 1', 'user', 'attend_submit'],
        'author_2@skdiit.test' => ['Test Author 2', 'user', 'attend_submit'],
        'author_3@skdiit.test' => ['Test Author 3', 'user', 'attend_submit'],
        'author_4@skdiit.test' => ['Test Author 4', 'user', 'attend_submit'],
        'author_5@skdiit.test' => ['Test Author 5', 'user', 'attend_submit'],
    ],

    // Email to test accounts (this domain) goes to this address instead, the subject saying who it was for
    // (App\Listeners\RedirectTestMail). Empty = send nothing to the test domain's addresses at all.
    'mail_domain' => 'skdiit.test',
    'mail_to' => env('DEMO_MAIL_TO', 'wongnarin.s@msu.ac.th'),
];
