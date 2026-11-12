<?php

namespace Database\Seeders;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for each role and the test authors (testing phase only, config/demo.php).
 * Run on its own with `php artisan db:seed --class=DemoAccountSeeder`; it never touches the real admin account.
 * Re-running resets the password to the demo one; papers and other data stay.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo.enabled')) {
            return;
        }

        foreach (config('demo.accounts') as $email => [$name, $role, $participation]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => config('demo.password'), 'role' => $role, 'affiliation' => 'msu', 'participation' => $participation]
            );

            // Registrants (authors) are filled in as if they had signed up: this year, consent, a meal, email confirmed
            if ($role === 'user') {
                $user->forceFill([
                    'registered_year' => $user->registered_year ?? Submission::conferenceYear(),
                    'pdpa_accepted_at' => $user->pdpa_accepted_at ?? now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'meal' => $user->meal ?? 'regular',
                ])->save();
            }
        }
    }
}
