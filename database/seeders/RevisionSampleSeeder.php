<?php

namespace Database\Seeders;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\User;
use Database\Seeders\Concerns\MakesSamplePapers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Five sample papers at each point of a revision (2026-11-12), to see the "author revising" and "revised" views:
 *   1. sent back at screening, author revising (no due date)        → queue "revising"
 *   2. sent back after review, due in 5 days                          → queue "revising"
 *   3. sent back after review, 3 days past the due date (red)         → queue "revising"
 *   4. revised after screening, waiting to be screened again          → queues "screening" and "revised"
 *   5. revised after review, waiting for the editor's decision        → queue "revised"
 * Authors author_4@ and author_5@, editor@ as the editor. Files are submissions/revisionsample-*. No emails are sent.
 * Add: php artisan sample:revision · Remove: php artisan sample:revision --remove (re-adding replaces them).
 */
class RevisionSampleSeeder extends Seeder
{
    use MakesSamplePapers;

    private const TAG = 'revisionsample';

    private const PAPERS = [
        ['author' => 'author_4@skdiit.test', 'track' => 1, 'title_th' => 'แอปพลิเคชันจองห้องประชุมด้วยปฏิทินกลางของคณะ', 'title_en' => 'A Shared-Calendar Meeting Room Booking App for Faculties', 'keywords_th' => 'จองห้อง, ปฏิทิน', 'keywords_en' => 'room booking, calendar', 'coauthors' => []],
        ['author' => 'author_4@skdiit.test', 'track' => 2, 'title_th' => 'การวิเคราะห์ความพึงพอใจผู้ใช้ห้องสมุดด้วยการเรียนรู้ของเครื่อง', 'title_en' => 'Analysing Library User Satisfaction with Machine Learning', 'keywords_th' => 'ห้องสมุด, การเรียนรู้ของเครื่อง', 'keywords_en' => 'library, machine learning', 'coauthors' => []],
        ['author' => 'author_5@skdiit.test', 'track' => 1, 'title_th' => 'ระบบแจ้งซ่อมออนไลน์สำหรับหอพักนักศึกษา', 'title_en' => 'An Online Repair Request System for Student Dormitories', 'keywords_th' => 'แจ้งซ่อม, หอพัก', 'keywords_en' => 'repair request, dormitory', 'coauthors' => []],
        ['author' => 'author_5@skdiit.test', 'track' => 2, 'title_th' => 'แดชบอร์ดติดตามผลการเรียนรายวิชาสำหรับอาจารย์ที่ปรึกษา', 'title_en' => 'A Course Performance Dashboard for Academic Advisors', 'keywords_th' => 'แดชบอร์ด, ผลการเรียน', 'keywords_en' => 'dashboard, academic performance', 'coauthors' => []],
        ['author' => 'author_4@skdiit.test', 'track' => 1, 'title_th' => 'ระบบลงเวลาปฏิบัติงานด้วยการจดจำใบหน้า', 'title_en' => 'A Face-Recognition Attendance System for Staff', 'keywords_th' => 'จดจำใบหน้า, ลงเวลา', 'keywords_en' => 'face recognition, attendance', 'coauthors' => []],
    ];

    public function run(): void
    {
        static::remove();
        SubmissionEvent::withoutEvents(fn () => DB::transaction(fn () => $this->create()));
        $this->command?->info('Added 5 sample papers at each point of a revision. Remove them with: php artisan sample:revision --remove');
    }

    private function create(): void
    {
        $editor = User::where('email', 'editor@skdiit.test')->firstOrFail();
        $start = now()->subDays(20);

        foreach (self::PAPERS as $i => $paper) {
            $author = User::where('email', $paper['author'])->firstOrFail();
            $p = $this->samplePaper($paper, $i + 1, $start->copy()->addHours($i));
            $at = fn (int $days) => $start->copy()->addDays($days);

            // Sent to the conference track and screened
            $this->move($p, SubmissionStatus::Screening, $editor, $at(2), SubmissionAction::JournalScreened);

            if (in_array($i, [0, 3], true)) {
                // Sent back at screening (no due date at this step)
                $this->move($p, SubmissionStatus::Revision, $editor, $at(4), SubmissionAction::Screened, 'Please follow the template and shorten the abstract.');
                if ($i === 3) {
                    $this->move($p, SubmissionStatus::Screening, $author, $at(8), SubmissionAction::Resubmitted);
                }

                continue;
            }

            // Passed screening, reviewed, then sent back after review with a due date
            $p->review_round = 1;
            $this->move($p, SubmissionStatus::InReview, $editor, $at(4), SubmissionAction::Screened);
            $due = match ($i) { 1 => today()->addDays(5), 2 => today()->subDays(3), default => today()->subDays(1) };
            $p->revision_due_date = $due;
            $this->move($p, SubmissionStatus::ReviewRevision, $editor, $at(10), SubmissionAction::Decided, 'Please address both reviewers.', ['due' => $due->toDateString()]);
            if ($i === 4) {
                $p->revision_due_date = null;
                $this->move($p, SubmissionStatus::Revised, $author, $at(15), SubmissionAction::Resubmitted);
            }
        }
    }

    private function move(Submission $p, SubmissionStatus $to, User $by, Carbon $when, SubmissionAction $action, ?string $comment = null, ?array $meta = null): void
    {
        $from = $p->status;
        $p->status = $to;
        $p->save();
        $this->sampleEvent($p, $action, $by, $when, $from, $comment, $meta);
    }
}
