<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Initial administrator account
        User::updateOrCreate(
            ['email' => 'wongnarin.s@msu.ac.th'],
            [
                'name' => 'Administrator',
                'password' => 'w123',
                'role' => 'admin',
                'affiliation' => 'msu',
                'participation' => 'attend',
            ]
        );

        // Demo accounts for each role (testing phase only, see config/demo.php)
        if (config('demo.enabled')) {
            foreach (config('demo.accounts') as $email => [$name, $role, $participation]) {
                User::updateOrCreate(
                    ['email' => $email],
                    ['name' => $name, 'password' => config('demo.password'), 'role' => $role, 'affiliation' => 'msu', 'participation' => $participation]
                );
            }
        }
    }
}
