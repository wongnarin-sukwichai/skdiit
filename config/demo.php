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
        'reviewer_2@skdiit.test' => ['Demo Reviewer 2', 'reviewer', 'attend'],
        'reviewer_3@skdiit.test' => ['Demo Reviewer 3', 'reviewer', 'attend'],
        'journal@skdiit.test' => ['Demo Journal', 'journal', 'attend'],
        'author@skdiit.test' => ['Demo Author', 'user', 'attend_submit'],
        'author_2@skdiit.test' => ['Demo Author 2', 'user', 'attend_submit'],
        'author_3@skdiit.test' => ['Demo Author 3', 'user', 'attend_submit'],
        'attendee@skdiit.test' => ['Demo Attendee', 'user', 'attend'],
    ],

    // Email to test accounts (this domain) goes to this address instead, the subject saying who it was for
    // (App\Listeners\RedirectTestMail). Empty = send nothing to the test domain's addresses at all.
    'mail_domain' => 'skdiit.test',
    'mail_to' => env('DEMO_MAIL_TO', 'wongnarin.s@msu.ac.th'),
];
