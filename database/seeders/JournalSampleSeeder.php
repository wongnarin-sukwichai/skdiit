<?php

namespace Database\Seeders;

use App\Models\SubmissionEvent;
use Database\Seeders\Concerns\MakesSamplePapers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Two sample papers waiting for the journal (status `submitted`), for trying the journal role on real data
 * (2026-10-20). Owned by author@ and author_2@ (*@skdiit.test); their files are stored as submissions/sample-*.
 * Add: php artisan sample:journal · Remove: php artisan sample:journal --remove (re-adding replaces them).
 * No workflow emails are sent.
 */
class JournalSampleSeeder extends Seeder
{
    use MakesSamplePapers;

    private const TAG = 'sample';

    private const PAPERS = [
        [
            'author' => 'author@skdiit.test',
            'track' => 1,
            'days' => 6,
            'title_th' => 'การพัฒนาระบบแนะนำหนังสือด้วยการเรียนรู้ของเครื่องสำหรับห้องสมุดมหาวิทยาลัย',
            'title_en' => 'A Machine-Learning Book Recommender for University Libraries',
            'keywords_th' => 'ระบบแนะนำ, การเรียนรู้ของเครื่อง, ห้องสมุดมหาวิทยาลัย',
            'keywords_en' => 'recommender system, machine learning, university library',
            'coauthors' => [['สุภาพร แก้วมณี', 'มหาวิทยาลัยมหาสารคาม', 'supaporn.k@example.ac.th']],
        ],
        [
            'author' => 'author_2@skdiit.test',
            'track' => 5,
            'days' => 3,
            'title_th' => 'แชตบอตปัญญาประดิษฐ์ช่วยตอบคำถามนักศึกษาเกี่ยวกับการลงทะเบียนเรียน',
            'title_en' => 'An AI Chatbot Answering Student Questions on Course Registration',
            'keywords_th' => 'แชตบอต, ปัญญาประดิษฐ์, การลงทะเบียนเรียน',
            'keywords_en' => 'chatbot, artificial intelligence, course registration',
            'coauthors' => [],
        ],
    ];

    public function run(): void
    {
        static::remove();

        // Logging "submitted" must not email anyone
        SubmissionEvent::withoutEvents(fn () => DB::transaction(function () {
            foreach (self::PAPERS as $i => $paper) {
                $this->create($paper, $i + 1);
            }
        }));

        $this->command?->info('Added '.count(self::PAPERS).' sample papers waiting for the journal. Remove them with: php artisan sample:journal --remove');
    }

    private function create(array $paper, int $number): void
    {
        $this->samplePaper($paper, $number, Carbon::now()->subDays($paper['days'])->setTime(10, 15 * $number));
    }
}
