<?php

namespace Database\Seeders;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\ReviewCriterion;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\User;
use Database\Seeders\Concerns\MakesSamplePapers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One sample paper in peer review (round 1), for trying the reviewer role on real data (2026-10-21).
 * Owned by author_3@ (created here if missing); track 1, reviewed by reviewer@ (not started, due in 7 days)
 * and reviewer_2@ (already sent: revise, with scores). History: submitted → passed on to the conference by the
 * editor after the journal → screening passed → reviewers assigned → reviewer 2 sent. Files are submissions/reviewsample-*.
 * Add: php artisan sample:review · Remove: php artisan sample:review --remove (re-adding replaces it). No emails are sent.
 */
class ReviewSampleSeeder extends Seeder
{
    use MakesSamplePapers;

    private const TAG = 'reviewsample';

    private const AUTHOR = 'author_3@skdiit.test';

    private const PAPER = [
        'author' => self::AUTHOR,
        'track' => 1,
        'title_th' => 'ระบบติดตามการยืมคืนครุภัณฑ์ด้วยคิวอาร์โค้ดสำหรับคณะวิชา',
        'title_en' => 'A QR-Code Equipment Loan Tracking System for University Faculties',
        'keywords_th' => 'คิวอาร์โค้ด, ครุภัณฑ์, ระบบสารสนเทศ',
        'keywords_en' => 'QR code, equipment loan, information system',
        'coauthors' => [['ธนากร ศรีสุข', 'มหาวิทยาลัยมหาสารคาม', 'thanakorn.s@example.ac.th']],
    ];

    public function run(): void
    {
        static::remove();
        $this->author();

        SubmissionEvent::withoutEvents(fn () => DB::transaction(fn () => $this->create()));

        $this->command?->info('Added 1 sample paper in review ('.self::AUTHOR.', reviewer@ to evaluate, reviewer_2@ sent). Remove it with: php artisan sample:review --remove');
    }

    /** author_3@ is a demo account (config/demo.php): make sure it exists, signed up for this year and verified. */
    private function author(): void
    {
        [$name, $role, $participation] = config('demo.accounts')[self::AUTHOR];
        $user = User::updateOrCreate(
            ['email' => self::AUTHOR],
            ['name' => $name, 'password' => config('demo.password'), 'role' => $role, 'affiliation' => 'msu', 'participation' => $participation]
        );
        $user->forceFill([
            'registered_year' => Submission::conferenceYear(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'pdpa_accepted_at' => $user->pdpa_accepted_at ?? now(),
        ])->save();
    }

    private function create(): void
    {
        $editor = User::where('email', 'editor@skdiit.test')->firstOrFail();
        $reviewers = [
            'pending' => User::where('email', 'reviewer@skdiit.test')->firstOrFail(),
            'sent' => User::where('email', 'reviewer_2@skdiit.test')->firstOrFail(),
        ];
        $day = fn (int $daysAgo, int $hour) => Carbon::now()->subDays($daysAgo)->setTime($hour, 0);

        $submission = $this->samplePaper(self::PAPER, 1, $day(9, 9));

        // The journal did not select it: the editor passed it on to the conference track after the journal deadline
        $this->status($submission, SubmissionStatus::Screening, SubmissionAction::JournalScreened, $editor, $day(6, 10));
        $submission->review_round = 1;
        $this->status($submission, SubmissionStatus::InReview, SubmissionAction::Screened, $editor, $day(5, 11));

        $assignedAt = $day(5, 13);
        $due = today()->addDays(7)->toDateString();
        foreach ($reviewers as $reviewer) {
            $review = $submission->reviews()->create(['reviewer_id' => $reviewer->id, 'assigned_by' => $editor->id, 'round' => 1, 'due_date' => $due]);
            $review->forceFill(['created_at' => $assignedAt, 'updated_at' => $assignedAt])->save();
            $this->sampleEvent($submission, SubmissionAction::ReviewerAssigned, $editor, $assignedAt, meta: [
                'reviewer' => $reviewer->name, 'reviewer_id' => $reviewer->id, 'due' => $due,
            ]);
        }

        // Reviewer 2 has already sent: revise, a 4/3 pattern of scores, and a confidential note to the editors
        $sentAt = $day(1, 15);
        $review = $submission->reviews()->where('reviewer_id', $reviewers['sent']->id)->first();
        $review->saveScores(ReviewCriterion::ordered()->pluck('id')->values()->mapWithKeys(fn ($id, $i) => [$id => $i % 2 ? 3 : 4])->all());
        $review->forceFill([
            'recommendation' => 'revise',
            'comment' => "งานมีประโยชน์และนำไปใช้ได้จริง แต่ควรปรับปรุงดังนี้\n1. ระบุจำนวนและลักษณะของกลุ่มผู้ใช้ที่ทดสอบให้ชัดเจน\n2. เพิ่มการเปรียบเทียบกับระบบยืมคืนเดิมของคณะ\n3. ตารางผลการประเมินความพึงพอใจควรแสดงค่าส่วนเบี่ยงเบนมาตรฐาน",
            'comment_to_editor' => 'ผลการทดลองยังน้อย แต่ปรับแก้ได้ในรอบเดียว',
            'submitted_at' => $sentAt, 'conflict_declared_at' => $sentAt, 'updated_at' => $sentAt,
        ])->save();
        $this->sampleEvent($submission, SubmissionAction::ReviewSubmitted, $reviewers['sent'], $sentAt, meta: ['recommendation' => 'revise']);
    }

    private function status(Submission $submission, SubmissionStatus $to, SubmissionAction $action, User $user, Carbon $when): void
    {
        $from = $submission->status;
        $submission->forceFill(['status' => $to, 'updated_at' => $when])->save();
        $this->sampleEvent($submission, $action, $user, $when, $from);
    }
}
