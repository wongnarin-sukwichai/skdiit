<?php

namespace Database\Seeders;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\Concerns\MakesSampleFiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Two sample papers waiting for the journal (status `submitted`), for trying the journal role on real data
 * (2026-10-20). Owned by author@ and author_2@ (*@skdiit.test); their files are stored as submissions/sample-*.
 * Add: php artisan sample:journal · Remove: php artisan sample:journal --remove (re-adding replaces them).
 * No workflow emails are sent.
 */
class JournalSampleSeeder extends Seeder
{
    use MakesSampleFiles;

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

    /** Delete the sample papers and their files; returns how many were removed. */
    public static function remove(): int
    {
        $papers = Submission::where('file_path', 'like', 'submissions/'.self::TAG.'-%')->get();
        foreach ($papers as $submission) {
            Storage::delete(array_filter([$submission->file_path, ...$submission->files()->pluck('path')]));
            $submission->delete();
        }

        return $papers->count();
    }

    private function create(array $paper, int $number): void
    {
        $author = User::where('email', $paper['author'])->firstOrFail();
        $track = Track::orderBy('id')->skip($paper['track'] - 1)->firstOrFail();
        $at = Carbon::now()->subDays($paper['days'])->setTime(10, 15 * $number);

        $submission = new Submission([
            'track_id' => $track->id,
            'title_th' => $paper['title_th'],
            'title_en' => $paper['title_en'],
            'abstract_th' => "บทความนี้นำเสนอ{$paper['title_th']} โดยเก็บข้อมูลจากผู้ใช้งานจริง ผลการทดลองพบว่าระบบช่วยลดเวลาและเพิ่มความพึงพอใจของผู้ใช้อย่างมีนัยสำคัญ พร้อมข้อเสนอแนะสำหรับการนำไปใช้ในหน่วยงานอื่น",
            'abstract_en' => 'This paper presents '.lcfirst($paper['title_en']).'. Data were collected from real users. The results show that the system saves time and significantly raises user satisfaction, with suggestions for other organisations.',
            'keywords_th' => $paper['keywords_th'],
            'keywords_en' => $paper['keywords_en'],
            ...$this->pdf("v1-{$number}", $paper['title_en'], self::TAG),
        ]);
        $submission->user()->associate($author);
        // Model events are off while seeding (withoutEvents covers every model), so year and code are set here
        $submission->forceFill(['year' => Submission::conferenceYear(), 'status' => SubmissionStatus::Submitted, 'review_round' => 0, 'created_at' => $at, 'updated_at' => $at])->save();
        $count = Submission::where('year', $submission->year)->where('id', '<=', $submission->id)->count();
        $submission->forceFill(['code' => sprintf('SKDIIT%02d-%04d', $submission->year % 100, $count)])->saveQuietly();

        $submission->authors()->createMany([
            ['position' => 1, 'name' => $author->name, 'affiliation' => 'มหาวิทยาลัยมหาสารคาม', 'email' => $author->email],
            ...array_map(fn ($c, $i) => ['position' => $i + 2, 'name' => $c[0], 'affiliation' => $c[1], 'email' => $c[2]], $paper['coauthors'], array_keys($paper['coauthors'])),
        ]);

        // Version 1: the PDF on the paper, plus Word and the Turnitin report
        $version = $submission->addManuscript($submission->only('file_path', 'file_name', 'file_size'), 'initial', $author);
        $word = $this->docx("v{$version}-{$number}", $paper['title_en'], self::TAG);
        $turnitin = [...$this->pdf("turnitin-{$number}", 'Turnitin similarity report: 6% - '.$paper['title_en'], self::TAG), 'file_name' => 'turnitin-report.pdf'];
        foreach (['word' => $word, 'turnitin' => $turnitin] as $kind => $file) {
            $submission->files()->create([
                'version' => $version, 'kind' => $kind, 'stage' => 'initial', 'round' => 0,
                'path' => $file['file_path'], 'name' => $file['file_name'], 'size' => $file['file_size'], 'user_id' => $author->id,
            ]);
        }
        $submission->files()->update(['created_at' => $at]);

        $event = new SubmissionEvent(['user_id' => $author->id, 'action' => SubmissionAction::Submitted]);
        $event->submission_id = $submission->id;
        $event->created_at = $at;
        $event->save();
    }
}
