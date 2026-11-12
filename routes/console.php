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

// Five sample papers at each point of a revision (Database\Seeders\RevisionSampleSeeder); --remove deletes them and their files
Artisan::command('sample:revision {--remove}', function () {
    if ($this->option('remove')) {
        $this->info('Removed '.\Database\Seeders\RevisionSampleSeeder::remove().' sample papers.');

        return;
    }
    (new \Database\Seeders\RevisionSampleSeeder)->setCommand($this)->run();
})->purpose('Add (or --remove) five sample papers at each point of a revision');

// Testing: keep the schedule's real dates (Settings › กำหนดการ) in a file, move them for a test, then put them back.
// `schedule:snapshot` saves, `--restore` writes the saved dates back, `--show` lists them. File: storage/app/private/schedule-snapshot.json
Artisan::command('schedule:snapshot {--restore} {--show}', function () {
    $keys = ['submission_opens', 'submission_deadline', 'journal_deadline', 'journal_answer_days', 'slides_cutoff_days', \App\Support\SchedulePlan::KEY];
    $disk = \Illuminate\Support\Facades\Storage::disk('local');
    $file = 'schedule-snapshot.json';

    if ($this->option('restore') || $this->option('show')) {
        if (! $disk->exists($file)) {
            $this->error('No snapshot saved yet.');

            return 1;
        }
        $snapshot = json_decode($disk->get($file), true);
        $this->table(['Setting', 'Saved value'], collect($snapshot['settings'])->map(fn ($v, $k) => [$k, json_encode($v)])->push(['conference date', $snapshot['conference_date']])->values());
        if ($this->option('show')) {
            return 0;
        }
        foreach ($snapshot['settings'] as $key => $value) {
            \App\Models\Setting::put($key, $value);
        }
        $conference = \App\Support\SiteContent::all()['conference'];
        \App\Support\SiteContent::put('conference', [...$conference, 'date' => $snapshot['conference_date']]);
        $this->info("Restored the schedule saved at {$snapshot['saved_at']}.");

        return 0;
    }

    $snapshot = [
        'saved_at' => now()->toDateTimeString(),
        'settings' => collect($keys)->mapWithKeys(fn ($key) => [$key => \App\Models\Setting::get($key)])->all(),
        'conference_date' => \App\Support\SiteContent::all()['conference']['date'] ?? null,
    ];
    $disk->put($file, json_encode($snapshot, JSON_PRETTY_PRINT));
    $this->table(['Setting', 'Saved value'], collect($snapshot['settings'])->map(fn ($v, $k) => [$k, json_encode($v)])->push(['conference date', $snapshot['conference_date']])->values());
    $this->info('Saved. Put these back later with: php artisan schedule:snapshot --restore');
})->purpose('Save (or --restore / --show) the schedule dates around a test');

// Journal offers (first gate, 2026-11-11): remind authors two days before the due date, and send unanswered offers to
// the conference track. Pages also expire lapsed offers when they load, so this only adds the reminder emails on time.
// Production: run `php artisan schedule:run` every minute from cron (Windows: Task Scheduler).
Artisan::command('journal:offers', function () {
    $reminded = \App\Models\Submission::remindJournalOffers();
    $expired = \App\Models\Submission::expireJournalOffers();
    $this->info("Reminded {$reminded}, expired {$expired} journal offers.");
})->purpose('Remind authors about journal offers and expire the unanswered ones');

\Illuminate\Support\Facades\Schedule::command('journal:offers')->hourly();
