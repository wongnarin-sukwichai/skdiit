<?php

namespace Database\Seeders;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\AcademicTitle;
use App\Models\Review;
use App\Models\ReviewCriterion;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample data for the testing phase, from resources/js/data/demo.json (the sample pages in mock.js read the
 * same file): 20 registrants (5 attend only, 15 authors), one paper per author covering the whole workflow
 * (journal first, then the conference track), reviewers with their tracks, reviews, history and small files.
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

    private User $journal;

    /** @var array<string, User> registrants by key */
    private array $people = [];

    /** @var array<string, User> */
    private array $reviewers = [];

    /** @var array<int, Track> keyed by position 1..5 */
    private array $tracks = [];

    private array $data;

    public function run(): void
    {
        $this->tracks = Track::orderBy('id')->get()->values()->mapWithKeys(fn ($t, $i) => [$i + 1 => $t])->all();
        if (count($this->tracks) < 5) {
            $this->command?->error('Seed the 5 tracks first (php artisan migrate).');

            return;
        }
        $this->data = json_decode(file_get_contents(resource_path('js/data/demo.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->clear();
        $this->accounts();
        $this->criteria();

        // Replaying the workflow must not send the workflow emails (withoutEvents turns off every model's events)
        SubmissionEvent::withoutEvents(fn () => DB::transaction(function () {
            foreach ($this->data['papers'] as $i => $paper) {
                $this->create($paper, $i + 1);
            }
        }));

        $this->command?->info('Created '.count($this->people).' registrants and '.count($this->data['papers']).' sample papers.');
    }

    /** n days before now, at a time of day that varies, so timelines read like real use. */
    private function at(int $daysAgo): Carbon
    {
        return Carbon::now()->subDays($daysAgo)->setTime(9 + $daysAgo % 8, ($daysAgo * 7) % 60);
    }

    /** Remove earlier sample papers (owned by *@skdiit.test accounts) and their files. */
    private function clear(): void
    {
        Submission::whereHas('user', fn ($q) => $q->where('email', 'like', '%@skdiit.test'))->get()
            ->each(function (Submission $submission) {
                Storage::delete(array_filter([
                    $submission->file_path, $submission->camera_ready_path, $submission->slides_path,
                    ...$submission->files()->pluck('path'), ...$submission->reviews()->pluck('attachment_path'),
                ]));
                $submission->delete();
            });
    }

    private function accounts(): void
    {
        $title = fn (?string $short) => $short ? AcademicTitle::where('short_th', $short)->value('id') : null;
        $user = fn (string $email, array $attributes, bool $verified = true, ?Carbon $created = null) => tap(User::updateOrCreate(['email' => $email], [
            'password' => self::PASSWORD,
            'participation' => 'attend',
            'affiliation' => 'msu',
            ...$attributes,
        ]), fn (User $u) => $u->forceFill([
            'email_verified_at' => $verified ? ($u->email_verified_at ?? now()) : null,
            'password_by_admin' => false,
            ...($created ? ['created_at' => $created] : []),
        ])->save());

        $this->editor = $user('editor@skdiit.test', ['name' => 'Demo Editor', 'role' => 'editor']);
        $this->journal = $user('journal@skdiit.test', ['name' => 'Demo Journal', 'role' => 'journal']);

        // Registrants: authors and attend-only, with their registration date
        foreach ($this->data['people'] as $person) {
            $this->people[$person['key']] = $user("{$person['key']}@skdiit.test", [
                'name' => $person['th'], 'role' => 'user', 'affiliation' => $person['affiliation'],
                'participation' => $person['participation'], 'registered_year' => Submission::conferenceYear(), 'phone' => $person['phone'], 'academic_title_id' => $title($person['title']),
            ], $person['verified'], $this->at($person['registered']));
        }

        // Reviewers and their tracks; one has not signed in yet (shows on the editor's home page)
        foreach ($this->data['reviewers'] as $reviewer) {
            $account = $user("{$reviewer['key']}@skdiit.test", [
                'name' => $reviewer['th'], 'role' => 'reviewer', 'academic_title_id' => $title($reviewer['title']),
            ], $reviewer['verified']);
            $account->tracks()->sync(array_map(fn ($n) => $this->tracks[$n]->id, $reviewer['tracks']));
            $this->reviewers[$reviewer['key']] = $account;
        }
    }

    /** Sample scoring criteria, only when admin has not set any (Settings › Review criteria). */
    private function criteria(): void
    {
        if (ReviewCriterion::exists()) {
            return;
        }
        foreach ($this->data['criteria'] as $i => [$th, $en]) {
            ReviewCriterion::create(['name_th' => $th, 'name_en' => $en, 'sort_order' => $i + 1]);
        }
    }

    /** Scores that fit the recommendation (accept 4-5 ... reject 1-2), varying by criterion. */
    private function scoresFor(string $recommendation): array
    {
        $base = ['accept' => 5, 'minor' => 4, 'major' => 3, 'reject' => 2][$recommendation];

        return ReviewCriterion::ordered()->pluck('id')->values()->mapWithKeys(fn ($id, $i) => [$id => max(1, $base - ($i % 2))])->all();
    }

    private function create(array $paper, int $number): void
    {
        $author = $this->people[$paper['author']];
        [$titleTh, $titleEn] = $paper['title'];
        [$keywordsTh, $keywordsEn] = $paper['keywords'];
        $submittedAt = $this->at($paper['submitted']);

        $submission = new Submission([
            'track_id' => $this->tracks[$paper['initialTrack'] ?? $paper['track']]->id,
            'title_th' => $titleTh,
            'title_en' => $titleEn,
            'abstract_th' => "บทความนี้นำเสนอ{$titleTh} โดยศึกษาจากกลุ่มตัวอย่างในพื้นที่ภาคตะวันออกเฉียงเหนือ ใช้วิธีวิจัยแบบผสานวิธี ผลการวิจัยพบว่าแนวทางที่นำเสนอช่วยเพิ่มประสิทธิภาพการทำงานได้อย่างมีนัยสำคัญ และเสนอแนวทางการนำไปใช้ในหน่วยงานที่มีบริบทใกล้เคียงกัน",
            'abstract_en' => 'This paper presents '.lcfirst($titleEn).'. A mixed-methods study was conducted with participants from north-eastern Thailand. The results show that the proposed approach significantly improves efficiency, and the paper offers guidelines for organisations in similar settings.',
            'keywords_th' => $keywordsTh,
            'keywords_en' => $keywordsEn,
            ...$this->pdf("v1-{$number}", $titleEn),
        ]);
        $submission->user()->associate($author);
        $submission->forceFill([
            // Model events are off while seeding (see run()), so the year is set here
            'year' => Submission::conferenceYear(),
            'status' => SubmissionStatus::Submitted,
            'review_round' => 0,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ])->save();
        // Same codes as the sample pages (SKDIIT27-0001 ... in demo.json order)
        $submission->forceFill(['code' => sprintf('SKDIIT%02d-%04d', $submission->year % 100, $number)])->saveQuietly();

        $submission->authors()->createMany([
            ['position' => 1, 'name' => $author->name, 'affiliation' => $this->affiliation($author->affiliation), 'email' => $author->email],
            ...array_map(fn ($c, $i) => ['position' => $i + 2, 'name' => $c[0], 'affiliation' => $c[1], 'email' => $c[2]], $paper['coauthors'] ?? [], array_keys($paper['coauthors'] ?? [])),
        ]);

        $this->event($submission, SubmissionAction::Submitted, $author, $submittedAt);
        $this->addVersion($submission, 'initial', $author, $submittedAt, withTurnitin: true);

        foreach ($paper['steps'] as $step) {
            $this->apply($submission, $step, $paper);
        }

        if ($submission->status->value !== $paper['status']) {
            $this->command?->warn("{$submission->code}: ended as {$submission->status->value}, demo.json says {$paper['status']}");
        }
    }

    /** The files of one manuscript version: PDF (already stored on the paper), Word, and the Turnitin report on a new paper. */
    private function addVersion(Submission $submission, string $stage, User $author, Carbon $when, bool $withTurnitin): int
    {
        $version = $submission->addManuscript($submission->only('file_path', 'file_name', 'file_size'), $stage, $author);
        $extra = [['word', $this->docx("v{$version}-".$submission->code, $submission->title_en)]];
        if ($withTurnitin) {
            $extra[] = ['turnitin', [...$this->pdf('turnitin-'.$submission->code, 'Turnitin similarity report: 8% - '.$submission->title_en), 'file_name' => 'turnitin-report.pdf']];
        }
        foreach ($extra as [$kind, $file]) {
            $submission->files()->create([
                'version' => $version, 'kind' => $kind, 'stage' => $stage, 'round' => (int) $submission->review_round,
                'path' => $file['file_path'], 'name' => $file['file_name'], 'size' => $file['file_size'], 'user_id' => $author->id,
            ]);
        }
        $submission->files()->where('version', $version)->update(['created_at' => $when]);

        return $version;
    }

    /** Replay one workflow step: change the paper the way the controllers do, and log it. */
    private function apply(Submission $submission, array $step, array $paper): void
    {
        $kind = $step[0];
        $when = $this->at($step[1]);
        $from = $submission->status;
        $author = $submission->user;

        switch ($kind) {
            case 'updated':
                $this->event($submission, SubmissionAction::Updated, $author, $when);
                break;

            case 'journalSelect': // the journal's first selection: select, or pass it on to the conference track
                $submission->status = $step[2] === 'select' ? SubmissionStatus::JournalSelected : SubmissionStatus::Screening;
                $submission->save();
                $this->event($submission, SubmissionAction::JournalScreened, $this->journal, $when, $from, $step[3] ?? null);
                break;

            case 'journalResult': // the journal's final answer for a selected paper
                $submission->status = $step[2] === 'accepted' ? SubmissionStatus::JournalAccepted : SubmissionStatus::Screening;
                $submission->save();
                $this->event($submission, SubmissionAction::JournalResult, $this->journal, $when, $from, $step[3] ?? null);
                break;

            case 'track':
                $old = $submission->track;
                $submission->update(['track_id' => $this->tracks[$step[2]]->id]);
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
                $this->review($submission, $key)->saveScores($this->scoresFor($recommendation));
                $this->review($submission, $key)->forceFill([
                    'recommendation' => $recommendation, 'comment' => $comment, 'comment_to_editor' => $step[5] ?? null,
                    'submitted_at' => $when, 'conflict_declared_at' => $when, 'updated_at' => $when,
                ])->save();
                $this->event($submission, SubmissionAction::ReviewSubmitted, $this->reviewers[$key], $when, meta: ['recommendation' => $recommendation]);
                break;

            case 'draft': // a reviewer saved but has not sent
                [, , $key, $recommendation, $comment] = $step;
                // A draft: only the first two criteria scored so far
                $this->review($submission, $key)->saveScores(array_slice($this->scoresFor($recommendation), 0, 2, preserve_keys: true));
                $this->review($submission, $key)->forceFill(['recommendation' => $recommendation, 'comment' => $comment, 'draft_saved_at' => $when])->save();
                break;

            case 'unavailable': // a reviewer told the editors they cannot review
                [, , $key, $reason] = $step;
                $this->review($submission, $key)->forceFill(['unavailable_at' => $when, 'unavailable_reason' => $reason])->save();
                $this->event($submission, SubmissionAction::ReviewUnavailable, $this->reviewers[$key], $when, meta: ['reviewer' => $this->reviewers[$key]->name, 'reason' => $reason]);
                break;

            case 'decide':
                $submission->status = SubmissionStatus::fromDecision($step[2]);
                $submission->revision_due_date = $submission->status->awaitsAuthorRevision() ? Carbon::today()->addDays($step[4]) : null;
                $submission->save();
                $this->event($submission, SubmissionAction::Decided, $this->editor, $when, $from, $step[3] ?? null,
                    $submission->revision_due_date ? ['due' => $submission->revision_due_date->toDateString()] : null);
                break;

            case 'resubmit': // revised PDF + Word, an answer to every reviewer of the decided round
                $submission->forceFill($this->pdf('v'.($submission->currentVersion() + 1).'-'.$submission->code, $submission->title_en.' (revised)'))->save();
                $version = $this->addVersion($submission, 'revision', $author, $when, withTurnitin: false);
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

            case 'round':
                $submission->status = SubmissionStatus::InReview;
                $submission->review_round++;
                $submission->save();
                $this->event($submission, SubmissionAction::ReviewRoundStarted, $this->editor, $when, $from, meta: ['round' => $submission->review_round]);
                break;

            case 'note':
                [, , $who, $body, $pinned] = $step + [4 => false];
                $note = $submission->notes()->make(['body' => $body, 'pinned' => $pinned]);
                $note->user_id = ($who === 'admin' ? User::where('role', 'admin')->first() : $this->editor)->id;
                $note->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
                $this->event($submission, SubmissionAction::NoteAdded, User::find($note->user_id), $when);
                break;

            case 'cameraReady':
                $file = $this->pdf('camera-ready-'.$submission->code, $submission->title_en);
                $submission->forceFill([
                    'camera_ready_path' => $file['file_path'],
                    'camera_ready_name' => 'camera-ready-'.$file['file_name'],
                    'camera_ready_size' => $file['file_size'],
                    'camera_ready_at' => $when,
                    'status' => SubmissionStatus::CameraReady,
                ])->save();
                $this->event($submission, SubmissionAction::CameraReadyUploaded, $author, $when, $from);
                break;

            case 'present':
                $submission->forceFill(['presentation_at' => Carbon::parse($step[2]), 'presentation_room' => $step[3]])->save();
                $this->event($submission, SubmissionAction::PresentationScheduled, $this->editor, $when, meta: [
                    'at' => $submission->presentation_at->toIso8601String(),
                    'room' => $submission->presentation_room,
                ]);
                break;

            case 'slides':
                $file = $this->pdf('slides-'.$submission->code, 'Slides: '.$submission->title_en);
                $submission->forceFill([
                    'slides_path' => $file['file_path'], 'slides_name' => 'slides-'.$file['file_name'], 'slides_size' => $file['file_size'], 'slides_at' => $when,
                ])->save();
                $this->event($submission, SubmissionAction::SlidesUploaded, $author, $when);
                break;
        }

        $submission->forceFill(['updated_at' => $when])->saveQuietly();
    }

    private function review(Submission $submission, string $key): Review
    {
        return Review::where('submission_id', $submission->id)->where('round', $submission->review_round)
            ->where('reviewer_id', $this->reviewers[$key]->id)->firstOrFail();
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
    private function pdf(string $prefix, string $title): array
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], 'SKDIIT 2027 sample: '.$title);
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

        return ['file_path' => $path, 'file_name' => Str::slug(Str::limit($title, 40, '')).'.pdf', 'file_size' => strlen($pdf)];
    }

    /** A minimal one-paragraph Word document stored like an uploaded file. */
    private function docx(string $prefix, string $title): array
    {
        $text = htmlspecialchars('SKDIIT 2027 sample: '.$title, ENT_XML1);
        $parts = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p></w:body></w:document>',
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($parts as $name => $xml) {
            $zip->addFromString($name, $xml);
        }
        $zip->close();

        $path = 'submissions/word/demo-'.$prefix.'-'.substr(md5($title), 0, 8).'.docx';
        Storage::put($path, file_get_contents($tmp));
        $size = filesize($tmp);
        unlink($tmp);

        return ['file_path' => $path, 'file_name' => Str::slug(Str::limit($title, 40, '')).'.docx', 'file_size' => $size];
    }

    private function affiliation(?string $key): string
    {
        return [
            'msu' => 'มหาวิทยาลัยมหาสารคาม', 'kku' => 'มหาวิทยาลัยขอนแก่น', 'cmu' => 'มหาวิทยาลัยเชียงใหม่',
            'nu' => 'มหาวิทยาลัยนเรศวร', 'rmu' => 'มหาวิทยาลัยราชภัฏมหาสารคาม', 'ubu' => 'มหาวิทยาลัยอุบลราชธานี',
            'kmitl' => 'สถาบันเทคโนโลยีพระจอมเกล้าเจ้าคุณทหารลาดกระบัง', 'tu' => 'มหาวิทยาลัยธรรมศาสตร์',
            'ksu' => 'มหาวิทยาลัยกาฬสินธุ์', 'obec' => 'สำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน',
        ][$key] ?? 'มหาวิทยาลัยมหาสารคาม';
    }
}
