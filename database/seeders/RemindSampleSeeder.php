<?php

namespace Database\Seeders;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\User;
use Database\Seeders\Concerns\MakesSamplePapers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Three sample papers waiting for their author (2026-11-13), to try "Remind the author":
 *  1. author_3@: offered to a journal, answer date passed two days ago (red on Selection);
 *  2. author_4@: revision requested after review, due three days ago (red badge in Submissions);
 *  3. author_5@: accepted, the camera-ready file not sent yet.
 * Files: submissions/remindsample-*. Add: php artisan sample:remind · Remove: --remove. No email is sent.
 */
class RemindSampleSeeder extends Seeder
{
    use MakesSamplePapers;

    private const TAG = 'remindsample';

    private const PAPERS = [
        'journal' => [
            'author' => 'author_3@skdiit.test', 'track' => 2,
            'title_th' => 'ระบบติดตามการยืมคืนครุภัณฑ์ด้วย QR Code สำหรับคณะ',
            'title_en' => 'A QR-Code Equipment Loan Tracker for Faculties',
            'keywords_th' => 'QR Code, ครุภัณฑ์, การยืมคืน', 'keywords_en' => 'QR code, equipment, loans',
            'coauthors' => [],
        ],
        'revision' => [
            'author' => 'author_4@skdiit.test', 'track' => 1,
            'title_th' => 'การวิเคราะห์พฤติกรรมการใช้ฐานข้อมูลออนไลน์ของนักศึกษาบัณฑิตศึกษา',
            'title_en' => 'How Graduate Students Use Online Databases',
            'keywords_th' => 'ฐานข้อมูลออนไลน์, พฤติกรรมสารสนเทศ, บัณฑิตศึกษา', 'keywords_en' => 'online databases, information behaviour, graduate students',
            'coauthors' => [['ธนากร ศรีสุข', 'มหาวิทยาลัยขอนแก่น', 'thanakorn.s@example.ac.th']],
        ],
        'cameraReady' => [
            'author' => 'author_5@skdiit.test', 'track' => 3,
            'title_th' => 'แดชบอร์ดติดตามการใช้พลังงานในอาคารมหาวิทยาลัยแบบเรียลไทม์',
            'title_en' => 'A Real-Time Energy Dashboard for University Buildings',
            'keywords_th' => 'แดชบอร์ด, พลังงาน, อาคารอัจฉริยะ', 'keywords_en' => 'dashboard, energy, smart building',
            'coauthors' => [],
        ],
    ];

    public function run(): void
    {
        static::remove();

        SubmissionEvent::withoutEvents(fn () => DB::transaction(function () {
            $editor = User::where('email', 'editor@skdiit.test')->firstOrFail();
            $number = 0;
            foreach (self::PAPERS as $case => $paper) {
                $this->{$case}($this->samplePaper($paper, ++$number, now()->subDays(25)->setTime(9, 10 * $number)), $editor);
            }
        }));

        $this->command?->info('Added 3 sample papers waiting for their author (journal answer, revision, camera-ready). Remove them with: php artisan sample:remind --remove');
    }

    private function at(int $daysAgo): Carbon
    {
        return now()->subDays($daysAgo)->setTime(14, 0);
    }

    /** Offered to a journal nine days ago; the author has not answered and the answer date passed two days ago. */
    private function journal(Submission $paper, User $editor): void
    {
        $journal = Journal::where('active', true)->orderBy('sort_order')->firstOrFail();
        $paper->forceFill(['status' => SubmissionStatus::JournalSelected, 'journal_id' => $journal->id, 'journal_answer_due' => today()->subDays(2)])->save();
        $this->sampleEvent($paper, SubmissionAction::JournalScreened, $editor, $this->at(9), SubmissionStatus::Submitted, null,
            ['journal_id' => $journal->id, 'journal' => $journal->name, 'due' => today()->subDays(2)->toDateString()]);
    }

    /** Sent to review, then "revise" after review with a due date three days ago; nothing sent back yet. */
    private function revision(Submission $paper, User $editor): void
    {
        $paper->forceFill(['status' => SubmissionStatus::InReview, 'review_round' => 1])->save();
        $this->sampleEvent($paper, SubmissionAction::Screened, $editor, $this->at(20), SubmissionStatus::Screening);
        $paper->forceFill(['status' => SubmissionStatus::ReviewRevision, 'revision_due_date' => today()->subDays(3)])->save();
        $this->sampleEvent($paper, SubmissionAction::Decided, $editor, $this->at(10), SubmissionStatus::InReview,
            'กรุณาปรับบทนำให้กระชับ และเพิ่มการอภิปรายผลเทียบกับงานวิจัยที่เกี่ยวข้อง', ['due' => today()->subDays(3)->toDateString()]);
    }

    /** Accepted five days ago; the author has not uploaded the camera-ready file. */
    private function cameraReady(Submission $paper, User $editor): void
    {
        $paper->forceFill(['status' => SubmissionStatus::InReview, 'review_round' => 1])->save();
        $this->sampleEvent($paper, SubmissionAction::Screened, $editor, $this->at(20), SubmissionStatus::Screening);
        $paper->forceFill(['status' => SubmissionStatus::Accepted])->save();
        $this->sampleEvent($paper, SubmissionAction::Decided, $editor, $this->at(5), SubmissionStatus::InReview, 'ยินดีด้วย บทความผ่านการประเมิน');
    }
}
