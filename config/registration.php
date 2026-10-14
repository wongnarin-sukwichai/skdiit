<?php

/*
|--------------------------------------------------------------------------
| Registration and sign-in
|--------------------------------------------------------------------------
|
| Registrants do not choose a password. The system emails a random 6-digit
| password with a link to the login page, which proves the email works
| (agreed 2026-10-12). The first successful sign-in marks the email as
| verified. Registrants can ask for a new password from the login modal and
| change it in "My details".
|
| show_dev_password: while no SMTP is set up (MAIL_MAILER=log), local testers
|   see the emailed password on screen. Must be false in production.
|
*/

return [
    'show_dev_password' => (bool) env('REGISTRATION_SHOW_DEV_PASSWORD', env('APP_ENV') === 'local'),

    // Failed sign-ins allowed per email + IP per minute (a 6-digit password is easy to guess without it)
    'login_attempts' => 5,
];
