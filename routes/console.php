<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Two sample papers waiting for the journal (Database\Seeders\JournalSampleSeeder); --remove deletes them and their files
Artisan::command('sample:journal {--remove}', function () {
    if ($this->option('remove')) {
        $this->info('Removed '.\Database\Seeders\JournalSampleSeeder::remove().' sample papers.');

        return;
    }
    (new \Database\Seeders\JournalSampleSeeder)->setCommand($this)->run();
})->purpose('Add (or --remove) two sample papers waiting for the journal');

// One sample paper in peer review (Database\Seeders\ReviewSampleSeeder): author_3@, reviewer@ to evaluate, reviewer_2@ sent
Artisan::command('sample:review {--remove}', function () {
    if ($this->option('remove')) {
        $this->info('Removed '.\Database\Seeders\ReviewSampleSeeder::remove().' sample papers.');

        return;
    }
    (new \Database\Seeders\ReviewSampleSeeder)->setCommand($this)->run();
})->purpose('Add (or --remove) one sample paper in peer review');
