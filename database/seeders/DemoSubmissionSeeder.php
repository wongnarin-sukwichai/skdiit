<?php

namespace Database\Seeders;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\AcademicTitle;
use App\Models\Review;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sample papers for the testing phase: 20 papers covering every SubmissionStatus,
 * with authors, reviewers, reviews, history and a small PDF each.
 *
 *   php artisan db:seed --class=DemoSubmissionSeeder
 *
 * Re-running replaces the previous sample papers. Every sample account is *@skdiit.test
 * (password 1234), so all of it goes away with the demo accounts before go-live.
 */
class DemoSubmissionSeeder extends Seeder
{
    private const PASSWORD = '1234';

    private User $editor;

    /** @var array<string, User> */
    private array $authors = [];

    /** @var array<string, User> */
    private array $reviewers = [];

    /** @var array<int, Track> keyed by position 1..5 */
    private array $tracks = [];

    public function run(): void
    {
        $this->tracks = Track::orderBy('id')->get()->values()->mapWithKeys(fn ($t, $i) => [$i + 1 => $t])->all();
        if (count($this->tracks) < 5) {
            $this->command?->error('Seed the 5 tracks first (php artisan migrate).');

            return;
        }

        $this->clear();
        $this->accounts();

        DB::transaction(function () {
            foreach ($this->papers() as $paper) {
                $this->create($paper);
            }
        });

        $this->command?->info('Created '.count($this->papers()).' sample papers.');
    }

    /** Remove earlier sample papers (owned by *@skdiit.test accounts) and their files. */
    private function clear(): void
    {
        Submission::whereHas('user', fn ($q) => $q->where('email', 'like', '%@skdiit.test'))->get()
            ->each(function (Submission $submission) {
                Storage::delete(array_filter([
                    $submission->file_path, $submission->camera_ready_path,
                    ...$submission->files()->pluck('path'), ...$submission->reviews()->pluck('attachment_path'),
                ]));
                $submission->delete();
            });
    }

    private function accounts(): void
    {
        $user = fn (string $email, array $attributes) => tap(User::updateOrCreate(['email' => $email], [
            'password' => self::PASSWORD,
            'participation' => 'attend',
            ...$attributes,
        ]), fn (User $u) => $u->forceFill(['email_verified_at' => $u->email_verified_at ?? now(), 'password_by_admin' => false])->save());

        $this->editor = $user('editor@skdiit.test', ['name' => 'Demo Editor', 'role' => 'editor', 'affiliation' => 'msu']);

        // Authors: the demo author plus a few more, so lists look real
        foreach ([
            'author' => ['Demo Author', 'msu'],
            'somchai' => ['สมชาย ใจดี', 'kku'],
            'pranee' => ['ปราณี ศรีสุข', 'msu'],
            'anan' => ['อนันต์ วงศ์ไทย', 'cmu'],
            'kanya' => ['กันยา พรหมมา', 'nu'],
            'wichai' => ['วิชัย แสงทอง', 'ubu'],
            'malee' => ['มาลี บุญมา', 'rmu'],
            'tanakorn' => ['ธนากร ศักดิ์ดี', 'kmitl'],
        ] as $key => [$name, $affiliation]) {
            $this->authors[$key] = $user("{$key}@skdiit.test", [
                'name' => $name, 'role' => 'user', 'affiliation' => $affiliation, 'participation' => 'attend_submit',
            ]);
        }

        // Reviewers and their tracks (2-3 per track); the demo reviewer covers tracks 1 and 2
        foreach ([
            'reviewer' => ['Demo Reviewer', 'รศ.', [1, 2]],
            'rv.suda' => ['สุดา มีสุข', 'ผศ.', [1, 3]],
            'rv.preecha' => ['ปรีชา คำแก้ว', 'รศ.', [2, 4]],
            'rv.nattaya' => ['ณัฐยา ทองดี', 'อ.', [3, 5]],
            'rv.kittisak' => ['กิตติศักดิ์ พันธ์ดี', 'ผศ.', [4, 5]],
            'rv.orawan' => ['อรวรรณ ศรีวงศ์', 'ศ.', [1, 5]],
        ] as $key => [$name, $title, $tracks]) {
            $reviewer = $user("{$key}@skdiit.test", ['name' => $name, 'role' => 'reviewer', 'affiliation' => 'msu', 'academic_title_id' => AcademicTitle::where('short_th', $title)->value('id')]);
            $reviewer->tracks()->sync(array_map(fn ($n) => $this->tracks[$n]->id, $tracks));
            $this->reviewers[$key] = $reviewer;
        }

        // A reviewer admin added who has not signed in yet (shows on the editor's home page)
        $user('rv.somsak@skdiit.test', ['name' => 'สมศักดิ์ ทองคำ', 'role' => 'reviewer', 'affiliation' => 'msu', 'academic_title_id' => AcademicTitle::where('short_th', 'ผศ.')->value('id')])
            ->forceFill(['email_verified_at' => null])->save();
        User::where('email', 'rv.somsak@skdiit.test')->first()->tracks()->sync([$this->tracks[2]->id]);
    }

    /**
     * One entry per paper. `steps` replays the workflow in order; each step becomes a history entry
     * with its own date (days before today), so the timeline reads like real use.
     */
    private function papers(): array
    {
        $at = fn (int $daysAgo) => Carbon::now()->subDays($daysAgo)->setTime(9 + $daysAgo % 8, ($daysAgo * 7) % 60);

        return [
            // ---- Waiting for screening ----
            [
                'author' => 'author', 'track' => 1, 'at' => $at(30),
                'title' => ['ระบบแนะนำหนังสือห้องสมุดด้วยการเรียนรู้ของเครื่อง', 'A Machine-Learning Book Recommender for University Libraries'],
                'keywords' => ['ระบบแนะนำ, ห้องสมุด, การเรียนรู้ของเครื่อง', 'recommender system, library, machine learning'],
                'coauthors' => [['ดร.สมศรี รักเรียน', 'มหาวิทยาลัยมหาสารคาม', null]],
                'steps' => [['updated', $at(25)]],
            ],
            [
                'author' => 'somchai', 'track' => 5, 'at' => $at(28),
                'title' => ['การจำแนกโรคใบข้าวด้วยโครงข่ายประสาทเทียมแบบคอนโวลูชัน', 'Rice Leaf Disease Classification with Convolutional Neural Networks'],
                'keywords' => ['ปัญญาประดิษฐ์, ภาพถ่าย, การเกษตร', 'deep learning, image classification, agriculture'],
                // Author picked Track 3; the editor moved it, so the author can no longer change it
                'steps' => [['track', $at(20), 3]],
                'initialTrack' => 3,
            ],
            [
                'author' => 'kanya', 'track' => 3, 'at' => $at(6),
                'title' => ['พฤติกรรมการรับข่าวสารผ่าน TikTok ของนักศึกษาปริญญาตรี', 'News Consumption on TikTok among Undergraduate Students'],
                'keywords' => ['สื่อสังคมออนไลน์, พฤติกรรมผู้รับสาร, นักศึกษา', 'social media, audience behaviour, students'],
                'steps' => [],
            ],

            // ---- Screening outcomes ----
            [
                'author' => 'pranee', 'track' => 2, 'at' => $at(40),
                'title' => ['ห้องเรียนอัจฉริยะด้วยเซนเซอร์ IoT เพื่อประหยัดพลังงาน', 'An IoT Smart Classroom for Energy Saving'],
                'keywords' => ['อินเทอร์เน็ตของสรรพสิ่ง, ห้องเรียนอัจฉริยะ, พลังงาน', 'IoT, smart classroom, energy'],
                'steps' => [['screen', $at(15), 'revise', 'กรุณาจัดรูปแบบตามแม่แบบของงานประชุม และเพิ่มบทคัดย่อภาษาอังกฤษให้ครบ 250 คำ']],
            ],
            [
                'author' => 'wichai', 'track' => 4, 'at' => $at(42),
                'title' => ['แนวทางการตลาดดิจิทัลสำหรับร้านกาแฟท้องถิ่น', 'Digital Marketing Guidelines for Local Coffee Shops'],
                'keywords' => ['การตลาดดิจิทัล, ธุรกิจขนาดเล็ก', 'digital marketing, small business'],
                'steps' => [['screen', $at(14), 'reject', 'เนื้อหาไม่ตรงกับขอบเขตของงานประชุม']],
            ],

            // ---- Peer review, round 1 ----
            [
                'author' => 'anan', 'track' => 1, 'at' => $at(45),
                'title' => ['การประเมินคุณภาพเมทาดาทาในคลังสถาบัน', 'Assessing Metadata Quality in Institutional Repositories'],
                'keywords' => ['เมทาดาทา, คลังสถาบัน, คุณภาพข้อมูล', 'metadata, institutional repository, data quality'],
                'steps' => [
                    ['screen', $at(13), 'pass'], ['assign', $at(12), 'reviewer', 14], ['assign', $at(12), 'rv.suda', 14],
                    // The demo reviewer has started: a saved draft
                    ['draft', $at(1), 'reviewer', 'minor', 'ภาพรวมดี ควรอธิบายเกณฑ์การให้คะแนนคุณภาพเมทาดาทาให้ชัดเจนขึ้น (ยังเขียนไม่เสร็จ)'],
                ],
            ],
            [
                'author' => 'malee', 'track' => 2, 'at' => $at(44),
                'title' => ['ระบบติดตามที่นั่งว่างในห้องสมุดแบบเรียลไทม์', 'Real-Time Seat Availability Tracking in Libraries'],
                'keywords' => ['ระบบติดตาม, พื้นที่อัจฉริยะ, ห้องสมุด', 'occupancy tracking, smart space, library'],
                'steps' => [
                    ['screen', $at(13), 'pass'], ['assign', $at(12), 'reviewer', 14], ['assign', $at(12), 'rv.preecha', 14],
                    ['review', $at(4), 'reviewer', 'minor', 'งานวิจัยมีประโยชน์ ควรอธิบายวิธีเก็บข้อมูลและขนาดตัวอย่างให้ชัดเจนขึ้น'],
                    // Both reviews are in: ready for the editor's decision
                    ['review', $at(2), 'rv.preecha', 'accept', 'บทความเขียนชัดเจน ผลการทดลองน่าเชื่อถือ'],
                ],
            ],
            [
                'author' => 'tanakorn', 'track' => 5, 'at' => $at(46),
                'title' => ['แชตบอตตอบคำถามงานทะเบียนด้วยโมเดลภาษาขนาดใหญ่', 'An LLM Chatbot for Student Registration Enquiries'],
                'keywords' => ['แชตบอต, โมเดลภาษาขนาดใหญ่, งานทะเบียน', 'chatbot, large language model, student services'],
                // One reviewer is overdue
                'steps' => [
                    ['screen', $at(20), 'pass'], ['assign', $at(19), 'rv.nattaya', 10], ['assign', $at(19), 'rv.orawan', 10],
                    ['review', $at(10), 'rv.orawan', 'major', 'ควรเปรียบเทียบกับวิธีเดิม และรายงานความถูกต้องของคำตอบอย่างเป็นระบบ', 'ข้อมูลทดสอบบางส่วนดูซ้ำกับบทความที่เคยตีพิมพ์ปีที่แล้ว ควรตรวจสอบการคัดลอกผลงาน'],
                    ['note', $at(3), 'editor', 'โทรหา อ.ณัฐยา แล้ว ขอเลื่อนส่งผลประเมินไป 3 วัน เพราะติดสอบปลายภาค', true],
                    ['note', $at(1), 'admin', 'ถ้าวันศุกร์ยังไม่ได้ผล ให้มอบหมายผู้ประเมินสำรองเพิ่ม'],
                ],
            ],
            [
                'author' => 'somchai', 'track' => 3, 'at' => $at(43),
                'title' => ['การรู้เท่าทันสื่อดิจิทัลของผู้สูงอายุในชุมชนเมือง', 'Digital Media Literacy of Older Adults in Urban Communities'],
                'keywords' => ['การรู้เท่าทันสื่อ, ผู้สูงอายุ', 'media literacy, older adults'],
                // Conflict of interest: a co-author's email matches reviewer rv.suda
                'coauthors' => [['สุดา มีสุข', 'มหาวิทยาลัยมหาสารคาม', 'rv.suda@skdiit.test']],
                'steps' => [['screen', $at(12), 'pass']],
            ],

            // ---- Editor decisions after review ----
            [
                'author' => 'pranee', 'track' => 1, 'at' => $at(50),
                'title' => ['การใช้ฐานข้อมูลออนไลน์ของนักศึกษาบัณฑิตศึกษา', 'Online Database Use by Graduate Students'],
                'keywords' => ['ฐานข้อมูลออนไลน์, บัณฑิตศึกษา', 'online databases, graduate students'],
                'steps' => [
                    ['screen', $at(30), 'pass'], ['assign', $at(29), 'rv.suda', 14], ['assign', $at(29), 'rv.orawan', 14],
                    ['review', $at(18), 'rv.suda', 'minor', 'ควรปรับตารางผลการวิจัยให้อ่านง่ายขึ้น'],
                    ['review', $at(17), 'rv.orawan', 'accept', 'บทความเขียนได้ดี มีข้อเสนอแนะเล็กน้อยเรื่องการอ้างอิง'],
                    ['decide', $at(15), 'minor', 'กรุณาแก้ไขตามข้อเสนอแนะของผู้ประเมิน', 10],
                ],
            ],
            [
                'author' => 'author', 'track' => 4, 'at' => $at(52),
                'title' => ['การยอมรับเทคโนโลยีการชำระเงินดิจิทัลของผู้ค้าในตลาดสด', 'Digital Payment Adoption among Fresh-Market Vendors'],
                'keywords' => ['การชำระเงินดิจิทัล, การยอมรับเทคโนโลยี', 'digital payment, technology acceptance'],
                // Revision is overdue (flagged, not blocked)
                'steps' => [
                    ['screen', $at(35), 'pass'], ['assign', $at(34), 'rv.preecha', 14], ['assign', $at(34), 'rv.kittisak', 14],
                    ['review', $at(24), 'rv.preecha', 'major', 'กรอบแนวคิดยังไม่ชัดเจน ควรใช้แบบจำลอง UTAUT ให้ครบทุกตัวแปร'],
                    ['review', $at(22), 'rv.kittisak', 'major', 'กลุ่มตัวอย่างน้อยเกินไป ควรเพิ่มและทดสอบความเชื่อมั่นของแบบสอบถาม'],
                    ['decide', $at(20), 'major', 'ผู้ประเมินทั้งสองท่านเสนอให้แก้ไขมาก กรุณาแก้ไขและส่งกลับภายในกำหนด', -3],
                ],
            ],
            [
                'author' => 'kanya', 'track' => 2, 'at' => $at(55),
                'title' => ['การออกแบบพื้นที่การเรียนรู้แบบยืดหยุ่นด้วยข้อมูลการใช้งานจริง', 'Data-Driven Design of Flexible Learning Spaces'],
                'keywords' => ['พื้นที่การเรียนรู้, การออกแบบ', 'learning space, design'],
                // Revised: the editor may decide directly or start round 2
                'steps' => [
                    ['screen', $at(38), 'pass'], ['assign', $at(37), 'reviewer', 14],
                    ['review', $at(27), 'reviewer', 'minor', 'ควรเพิ่มภาพประกอบผังพื้นที่ก่อนและหลังการปรับปรุง'],
                    ['decide', $at(25), 'minor', null, 10], ['resubmit', $at(18)],
                    ['note', $at(17), 'editor', 'ผู้เขียนแจ้งทางโทรศัพท์ว่าแก้ครบแล้ว รอ Editor ตัดสินผล'],
                ],
            ],
            [
                'author' => 'anan', 'track' => 5, 'at' => $at(60),
                'title' => ['การตรวจจับข่าวปลอมภาษาไทยด้วยโมเดล Transformer', 'Thai Fake News Detection with Transformer Models'],
                'keywords' => ['ข่าวปลอม, ประมวลผลภาษาธรรมชาติ', 'fake news, natural language processing'],
                // Second review round in progress
                'steps' => [
                    ['screen', $at(45), 'pass'], ['assign', $at(44), 'rv.nattaya', 14],
                    ['review', $at(33), 'rv.nattaya', 'major', 'ควรเพิ่มชุดข้อมูลทดสอบจากหลายแหล่ง'],
                    ['decide', $at(31), 'major', null, 14], ['resubmit', $at(20)], ['round', $at(18)],
                    ['assign', $at(18), 'rv.nattaya', 25], ['assign', $at(18), 'rv.kittisak', 25],
                    ['unavailable', $at(2), 'rv.kittisak', 'ไม่ตรงกับความเชี่ยวชาญ (งานด้าน NLP) แนะนำ อ.ณัฐยา หรือ ศ.อรวรรณ แทน'],
                ],
            ],
            [
                'author' => 'author', 'track' => 2, 'at' => $at(58),
                'title' => ['ต้นแบบระบบควบคุมแสงสว่างอัตโนมัติในอาคารเรียน', 'An Automatic Lighting Control Prototype for Classroom Buildings'],
                'keywords' => ['ระบบอัตโนมัติ, อาคารอัจฉริยะ', 'automation, smart building'],
                // Accepted: waiting for the author to choose Option A or B
                'steps' => [
                    ['screen', $at(40), 'pass'], ['assign', $at(39), 'reviewer', 14], ['assign', $at(39), 'rv.preecha', 14],
                    ['review', $at(30), 'reviewer', 'accept', 'บทความสมบูรณ์ ผลการทดลองชัดเจน'],
                    ['review', $at(29), 'rv.preecha', 'accept', 'เห็นควรรับตีพิมพ์'],
                    ['decide', $at(27), 'accept', 'ยินดีด้วย บทความของท่านผ่านการพิจารณา'],
                ],
            ],
            [
                'author' => 'wichai', 'track' => 3, 'at' => $at(57),
                'title' => ['อิทธิพลของอินฟลูเอนเซอร์ต่อการตัดสินใจเลือกมหาวิทยาลัย', 'Influencer Impact on University Choice'],
                'keywords' => ['อินฟลูเอนเซอร์, การตัดสินใจ', 'influencer, decision making'],
                'steps' => [
                    ['screen', $at(40), 'pass'], ['assign', $at(39), 'rv.suda', 14],
                    ['review', $at(30), 'rv.suda', 'reject', 'วิธีวิจัยไม่เหมาะสมกับคำถามการวิจัย และขาดการทบทวนวรรณกรรม'],
                    ['decide', $at(28), 'reject', 'ขอบคุณที่ส่งบทความ บทความยังไม่ผ่านเกณฑ์การพิจารณาในครั้งนี้'],
                ],
            ],

            // ---- Publication ----
            [
                'author' => 'malee', 'track' => 4, 'at' => $at(62),
                'title' => ['การเปลี่ยนผ่านสู่ดิจิทัลของงานสารบรรณในองค์การบริหารส่วนตำบล', 'Digital Transformation of Records Management in Local Government'],
                'keywords' => ['การเปลี่ยนผ่านสู่ดิจิทัล, งานสารบรรณ', 'digital transformation, records management'],
                'steps' => [...$this->accepted($at, 'rv.kittisak'), ['option', $at(20), 'a']],
            ],
            [
                'author' => 'tanakorn', 'track' => 5, 'at' => $at(65),
                'title' => ['การพยากรณ์จำนวนผู้ใช้บริการห้องสมุดด้วยอนุกรมเวลา', 'Forecasting Library Visits with Time-Series Models'],
                'keywords' => ['อนุกรมเวลา, การพยากรณ์', 'time series, forecasting'],
                'steps' => [...$this->accepted($at, 'rv.orawan'), ['option', $at(22), 'a'], ['cameraReady', $at(16)], ['present', $at(5), '2027-01-28 10:45', 'ห้อง 301 อาคาร IT']],
            ],
            [
                'author' => 'pranee', 'track' => 1, 'at' => $at(66),
                'title' => ['การพัฒนาบริการสารสนเทศเชิงรุกสำหรับนักวิจัย', 'Proactive Information Services for Researchers'],
                'keywords' => ['บริการสารสนเทศ, นักวิจัย', 'information services, researchers'],
                'steps' => [...$this->accepted($at, 'reviewer'), ['option', $at(21), 'b', 'วารสารสารสนเทศศาสตร์ มมส']],
            ],
            [
                'author' => 'kanya', 'track' => 3, 'at' => $at(70),
                'title' => ['การเล่าเรื่องดิจิทัลเพื่อส่งเสริมการท่องเที่ยวชุมชน', 'Digital Storytelling for Community Tourism'],
                'keywords' => ['การเล่าเรื่องดิจิทัล, การท่องเที่ยวชุมชน', 'digital storytelling, community tourism'],
                'steps' => [
                    ...$this->accepted($at, 'rv.nattaya'), ['option', $at(24), 'b', 'Journal of Information Science Research and Practice'],
                    ['journal', $at(9), 'accepted', 'วารสารตอบรับเพื่อตีพิมพ์ฉบับที่ 1/2570'], ['present', $at(4), '2027-01-28 13:00', 'ห้อง 302 อาคาร IT'],
                ],
            ],
            [
                'author' => 'somchai', 'track' => 4, 'at' => $at(72),
                'title' => ['ปัจจัยความสำเร็จของการใช้ระบบ ERP ในมหาวิทยาลัย', 'Success Factors of ERP Use in Universities'],
                'keywords' => ['ERP, ปัจจัยความสำเร็จ', 'ERP, success factors'],
                // The journal declined, so the paper moved to Option A
                'steps' => [
                    ...$this->accepted($at, 'rv.preecha'), ['option', $at(25), 'b', 'วารสารวิทยาการจัดการ'],
                    ['journal', $at(8), 'rejected', 'วารสารแจ้งว่าไม่ตรงขอบเขต จึงย้ายไปตีพิมพ์ใน Proceeding'],
                ],
            ],
        ];
    }

    /** Screening, one review and an "accept" decision: the start of every publication sample. */
    private function accepted(\Closure $at, string $reviewer): array
    {
        return [
            ['screen', $at(48), 'pass'], ['assign', $at(47), $reviewer, 14],
            ['review', $at(38), $reviewer, 'accept', 'บทความมีคุณภาพ เห็นควรรับตีพิมพ์'],
            ['decide', $at(35), 'accept', 'ยินดีด้วย บทความของท่านผ่านการพิจารณา'],
        ];
    }

    private function create(array $paper): void
    {
        $author = $this->authors[$paper['author']];
        [$titleTh, $titleEn] = $paper['title'];
        [$keywordsTh, $keywordsEn] = $paper['keywords'];
        $submittedAt = $paper['at'];

        $submission = new Submission([
            'track_id' => $this->tracks[$paper['initialTrack'] ?? $paper['track']]->id,
            'title_th' => $titleTh,
            'title_en' => $titleEn,
            'abstract_th' => "บทความนี้นำเสนอ{$titleTh} โดยศึกษาจากกลุ่มตัวอย่างในพื้นที่ภาคตะวันออกเฉียงเหนือ ใช้วิธีวิจัยแบบผสานวิธี ผลการวิจัยพบว่าแนวทางที่นำเสนอช่วยเพิ่มประสิทธิภาพการทำงานได้อย่างมีนัยสำคัญ และเสนอแนวทางการนำไปใช้ในหน่วยงานที่มีบริบทใกล้เคียงกัน",
            'abstract_en' => "This paper presents ".lcfirst($titleEn).'. A mixed-methods study was conducted with participants from north-eastern Thailand. The results show that the proposed approach significantly improves efficiency, and the paper offers guidelines for organisations in similar settings.',
            'keywords_th' => $keywordsTh,
            'keywords_en' => $keywordsEn,
            ...$this->file($paper['author'], $titleEn),
        ]);
        $submission->user()->associate($author);
        $submission->forceFill([
            'status' => SubmissionStatus::Submitted,
            'review_round' => 0,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ])->save();

        $submission->authors()->createMany([
            ['position' => 1, 'name' => $author->name, 'affiliation' => $this->affiliation($author->affiliation), 'email' => $author->email],
            ...array_map(fn ($c, $i) => ['position' => $i + 2, 'name' => $c[0], 'affiliation' => $c[1], 'email' => $c[2]], $paper['coauthors'] ?? [], array_keys($paper['coauthors'] ?? [])),
        ]);

        $this->event($submission, SubmissionAction::Submitted, $author, $submittedAt);
        $submission->addManuscript($submission->only('file_path', 'file_name', 'file_size'), 'initial', $author);
        $submission->files()->latest('id')->first()->forceFill(['created_at' => $submittedAt])->save();

        foreach ($paper['steps'] as $step) {
            $this->apply($submission, $step, $paper);
        }
    }

    /** Replay one workflow step: change the paper the way the controllers do, and log it. */
    private function apply(Submission $submission, array $step, array $paper): void
    {
        [$kind, $when] = $step;
        $from = $submission->status;
        $author = $submission->user;

        switch ($kind) {
            case 'updated':
                $this->event($submission, SubmissionAction::Updated, $author, $when);
                break;

            case 'track':
                $old = $submission->track;
                $submission->update(['track_id' => $this->tracks[$paper['track']]->id]);
                $this->event($submission, SubmissionAction::TrackChanged, $this->editor, $when, meta: [
                    'from' => $old->toClient()['name'],
                    'to' => $submission->load('track')->track->toClient()['name'],
                ]);
                break;

            case 'screen':
                $submission->status = SubmissionStatus::fromScreening($step[2]);
                if ($submission->status === SubmissionStatus::InReview) {
                    $submission->review_round = 1;
                }
                $submission->save();
                $this->event($submission, SubmissionAction::Screened, $this->editor, $when, $from, $step[3] ?? null);
                break;

            case 'assign':
                [, , $key, $dueInDays] = $step;
                $review = $submission->reviews()->create([
                    'reviewer_id' => $this->reviewers[$key]->id,
                    'assigned_by' => $this->editor->id,
                    'round' => $submission->review_round,
                    'due_date' => $when->copy()->addDays($dueInDays)->toDateString(),
                ]);
                $review->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
                $this->event($submission, SubmissionAction::ReviewerAssigned, $this->editor, $when, meta: [
                    'reviewer' => $this->reviewers[$key]->name,
                    'due' => $review->due_date->toDateString(),
                ]);
                break;

            case 'review':
                [, , $key, $recommendation, $comment] = $step;
                Review::where('submission_id', $submission->id)->where('round', $submission->review_round)
                    ->where('reviewer_id', $this->reviewers[$key]->id)->firstOrFail()
                    ->forceFill([
                        'recommendation' => $recommendation, 'comment' => $comment, 'comment_to_editor' => $step[5] ?? null,
                        'submitted_at' => $when, 'conflict_declared_at' => $when, 'updated_at' => $when,
                    ])->save();
                $this->event($submission, SubmissionAction::ReviewSubmitted, $this->reviewers[$key], $when, meta: ['recommendation' => $recommendation]);
                break;

            case 'decide':
                $submission->status = SubmissionStatus::fromDecision($step[2]);
                $submission->revision_due_date = $submission->status->awaitsAuthorRevision() ? Carbon::today()->addDays($step[4]) : null;
                $submission->save();
                $this->event($submission, SubmissionAction::Decided, $this->editor, $when, $from, $step[3] ?? null,
                    $submission->revision_due_date ? ['due' => $submission->revision_due_date->toDateString()] : null);
                break;

            case 'resubmit':
                $stored = $this->file('v'.((int) $submission->files()->max('version') + 1), $submission->title_en.' (revised)');
                $submission->forceFill($stored)->save();
                $version = $submission->addManuscript($stored, 'revision', $author);
                $submission->files()->latest('id')->first()->forceFill(['created_at' => $when])->save();
                $answers = [
                    'ขอบคุณสำหรับข้อเสนอแนะ ได้เพิ่มรายละเอียดวิธีเก็บข้อมูลและขนาดกลุ่มตัวอย่างไว้ในหัวข้อ 3.2 (หน้า 4) แล้ว',
                    'ปรับตารางผลการวิจัยใหม่ให้อ่านง่ายขึ้น และเพิ่มคำอธิบายใต้ตาราง (หน้า 6–7)',
                ];
                $submission->reviews()->whereNotNull('submitted_at')->where('round', $submission->review_round)->orderBy('id')->get()
                    ->each(fn (Review $review, int $i) => $submission->responses()->create([
                        'version' => $version, 'review_id' => $review->id, 'body' => $answers[$i % count($answers)],
                    ]));
                $submission->responses()->create(['version' => $version, 'review_id' => null, 'body' => 'แก้ไขครบทุกข้อตามข้อเสนอแนะแล้ว ส่วนที่แก้ไขทำตัวอักษรสีแดงไว้ในไฟล์']);
                $submission->status = SubmissionStatus::Revised;
                $submission->save();
                $this->event($submission, SubmissionAction::Resubmitted, $author, $when, $from);
                break;

            case 'draft': // a reviewer saved but has not sent
                [, , $key, $recommendation, $comment] = $step;
                Review::where('submission_id', $submission->id)->where('round', $submission->review_round)
                    ->where('reviewer_id', $this->reviewers[$key]->id)->firstOrFail()
                    ->forceFill(['recommendation' => $recommendation, 'comment' => $comment, 'draft_saved_at' => $when])->save();
                break;

            case 'unavailable': // a reviewer told the editors they cannot review
                [, , $key, $reason] = $step;
                Review::where('submission_id', $submission->id)->where('round', $submission->review_round)
                    ->where('reviewer_id', $this->reviewers[$key]->id)->firstOrFail()
                    ->forceFill(['unavailable_at' => $when, 'unavailable_reason' => $reason])->save();
                $this->event($submission, SubmissionAction::ReviewUnavailable, $this->reviewers[$key], $when, meta: ['reviewer' => $this->reviewers[$key]->name, 'reason' => $reason]);
                break;

            case 'note':
                [, , $who, $body, $pinned] = $step + [4 => false];
                $note = $submission->notes()->make(['body' => $body, 'pinned' => $pinned]);
                $note->user_id = ($who === 'admin' ? User::where('role', 'admin')->first() : $this->editor)->id;
                $note->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
                $this->event($submission, SubmissionAction::NoteAdded, User::find($note->user_id), $when);
                break;

            case 'round':
                $submission->status = SubmissionStatus::InReview;
                $submission->review_round++;
                $submission->save();
                $this->event($submission, SubmissionAction::ReviewRoundStarted, $this->editor, $when, $from, meta: ['round' => $submission->review_round]);
                break;

            case 'option':
                $submission->publish_option = $step[2];
                $submission->journal_name = $step[3] ?? null;
                $submission->status = $step[2] === 'a' ? SubmissionStatus::OptionA : SubmissionStatus::OptionB;
                $submission->save();
                $this->event($submission, SubmissionAction::OptionChosen, $author, $when, $from, meta: ['option' => $step[2]]);
                break;

            case 'cameraReady':
                $file = $this->file('camera-ready', $submission->title_en);
                $submission->forceFill([
                    'camera_ready_path' => $file['file_path'],
                    'camera_ready_name' => 'camera-ready-'.$file['file_name'],
                    'camera_ready_size' => $file['file_size'],
                    'camera_ready_at' => $when,
                    'status' => SubmissionStatus::CameraReady,
                ])->save();
                $this->event($submission, SubmissionAction::CameraReadyUploaded, $author, $when, $from);
                break;

            case 'journal':
                [, , $result, $comment] = $step;
                if ($result === 'accepted') {
                    $submission->status = SubmissionStatus::JournalAccepted;
                } else {
                    $submission->status = SubmissionStatus::OptionA;
                    $submission->publish_option = 'a';
                }
                $submission->save();
                $this->event($submission, SubmissionAction::JournalResult, $this->editor, $when, $from, $comment, [
                    'journal' => $submission->journal_name,
                    'result' => $result,
                ]);
                break;

            case 'present':
                $submission->forceFill(['presentation_at' => Carbon::parse($step[2]), 'presentation_room' => $step[3]])->save();
                $this->event($submission, SubmissionAction::PresentationScheduled, $this->editor, $when, meta: [
                    'at' => $submission->presentation_at->toIso8601String(),
                    'room' => $submission->presentation_room,
                ]);
                break;
        }

        $submission->forceFill(['updated_at' => $when])->saveQuietly();
    }

    /** History entry with its own date (Submission::log() always uses now). */
    private function event(Submission $submission, SubmissionAction $action, User $user, Carbon $when, ?SubmissionStatus $from = null, ?string $comment = null, ?array $meta = null): void
    {
        $event = new SubmissionEvent([
            'user_id' => $user->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $from ? $submission->status->value : null,
            'comment' => $comment,
            'meta' => $meta,
        ]);
        $event->submission_id = $submission->id;
        $event->created_at = $when;
        $event->save();
    }

    /** A one-page PDF stored like an uploaded file. */
    private function file(string $prefix, string $title): array
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], 'SKDIIT 2027 sample paper: '.$title);
        $stream = "BT /F1 14 Tf 60 780 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $path = 'submissions/demo-'.$prefix.'-'.substr(md5($title), 0, 8).'.pdf';
        Storage::put($path, $pdf);

        return ['file_path' => $path, 'file_name' => \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($title, 40, '')).'.pdf', 'file_size' => strlen($pdf)];
    }

    private function affiliation(?string $key): string
    {
        return [
            'msu' => 'มหาวิทยาลัยมหาสารคาม', 'kku' => 'มหาวิทยาลัยขอนแก่น', 'cmu' => 'มหาวิทยาลัยเชียงใหม่',
            'nu' => 'มหาวิทยาลัยนเรศวร', 'rmu' => 'มหาวิทยาลัยราชภัฏมหาสารคาม', 'ubu' => 'มหาวิทยาลัยอุบลราชธานี',
            'kmitl' => 'สถาบันเทคโนโลยีพระจอมเกล้าเจ้าคุณทหารลาดกระบัง',
        ][$key] ?? 'มหาวิทยาลัยมหาสารคาม';
    }
}
