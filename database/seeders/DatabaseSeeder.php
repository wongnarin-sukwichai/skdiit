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

        // Demo accounts for each role and the test authors (testing phase only, see config/demo.php)
        $this->call(DemoAccountSeeder::class);
    }
}
