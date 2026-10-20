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
