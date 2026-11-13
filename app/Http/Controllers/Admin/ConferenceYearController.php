<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use App\Support\SchedulePlan;
use App\Support\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Settings › Conference year (admin):
 *  - a new conference year (item 39): finished papers of the year go to the article archive, the work lists start
 *    empty, paper codes restart (SKDIIT28-0001), accounts, reviewers and tracks stay, the submission period is cleared;
 *  - the backup download (item 41): the database as SQL and every uploaded file, in one ZIP.
 */
class ConferenceYearController extends Controller
{
    /** What a new year would do, for the Settings tab. */
    public static function summary(): array
    {
        $year = Submission::conferenceYear();
        $papers = Submission::currentYear();

        return [
            'year' => $year,
            'papers' => (clone $papers)->count(),
            'toArchive' => (clone $papers)->whereIn('status', [SubmissionStatus::CameraReady, SubmissionStatus::JournalAccepted])->count(),
            'unfinished' => (clone $papers)->whereNotIn('status', [SubmissionStatus::CameraReady, SubmissionStatus::JournalAccepted, SubmissionStatus::Rejected, SubmissionStatus::ReviewRejected])->count(),
            'registrants' => User::where('role', 'user')->where('registered_year', $year)->count(),
            'reviewers' => User::where('role', 'reviewer')->whereNull('suspended_at')->count(),
            'archived' => Submission::archived()->count(),
        ];
    }

    public function rollover(Request $request): RedirectResponse
    {
        $current = Submission::conferenceYear();
        $data = $request->validate([
            'year' => ['required', 'integer', 'gt:'.$current, 'max:'.($current + 5)],
            // Typed by admin to confirm: the new year
            'confirm' => ['required', 'same:year'],
            // Reviewers usually change every year: suspend them all, admin re-opens those who continue (2026-11-13)
            'suspend_reviewers' => ['boolean'],
        ], ['required' => 'validation.required', 'gt' => 'validation.year_after', 'max' => 'validation.year_after', 'same' => 'validation.confirm_year', 'integer' => 'validation.integer']);
        $new = (int) $data['year'];

        $suspend = $request->boolean('suspend_reviewers');
        DB::transaction(function () use ($current, $new, $suspend) {
            Setting::put('conference_year', $new);
            // The new year sets its own dates
            foreach (['submission_opens', 'submission_deadline', 'journal_deadline', SchedulePlan::KEY] as $key) {
                Setting::put($key, null);
            }
            // "SKDIIT 2027" becomes "SKDIIT 2028" (admin checks the rest in Settings › Conference)
            $conference = SiteContent::all()['conference'];
            $conference['shortName'] = str_replace((string) $current, (string) $new, $conference['shortName']);
            SiteContent::put('conference', $conference);
            if ($suspend) {
                User::where('role', 'reviewer')->whereNull('suspended_at')->update(['suspended_at' => now()]);
            }
        });

        return back()->with('success', 'admin.year.started');
    }

    /** One ZIP: database.sql (structure and data) and files/ (everything uploaded to the private disk). */
    public function backup(Request $request): BinaryFileResponse
    {
        @set_time_limit(600);
        $zipPath = tempnam(sys_get_temp_dir(), 'skdiit-backup');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);

        $zip->addFromString('database.sql', $this->dumpDatabase());
        foreach (Storage::allFiles() as $file) {
            $zip->addFile(Storage::path($file), 'files/'.$file);
        }
        $zip->addFromString('README.txt', 'SKDIIT backup '.now()->toDateTimeString()."\n\ndatabase.sql: import into an empty MySQL database (e.g. mysql -u root skdiit_db < database.sql).\nfiles/: copy into storage/app/private/.\n");
        $zip->close();

        ActivityLog::record('system', 'backup_downloaded');

        return response()->download($zipPath, 'skdiit-backup-'.now()->format('Ymd-His').'.zip')->deleteFileAfterSend();
    }

    /** CREATE TABLE and INSERT statements for every table (no shell tools needed). */
    private function dumpDatabase(): string
    {
        $pdo = DB::connection()->getPdo();
        $sql = '-- SKDIIT database backup '.now()->toDateTimeString()."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";

        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n";

            foreach (DB::table($table)->cursor() as $record) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $record);
                $sql .= "INSERT INTO `{$table}` (`".implode('`, `', array_keys((array) $record)).'`) VALUES ('.implode(', ', $values).");\n";
            }
            $sql .= "\n";
        }

        return $sql."SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
