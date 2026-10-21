<?php

namespace Database\Seeders\Concerns;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A sample paper as an author sends it (status `submitted`, PDF + Word + Turnitin as version 1), and its history entries
 * with their own dates. Files are stored as submissions/{TAG}-*, which is how remove() finds them again.
 * Used by JournalSampleSeeder and ReviewSampleSeeder; run inside SubmissionEvent::withoutEvents so nobody is emailed.
 */
trait MakesSamplePapers
{
    use MakesSampleFiles;

    /** Delete this seeder's sample papers and their files; returns how many were removed. */
    public static function remove(): int
    {
        $papers = Submission::where('file_path', 'like', 'submissions/'.static::TAG.'-%')->get();
        foreach ($papers as $submission) {
            Storage::delete(array_filter([$submission->file_path, ...$submission->files()->pluck('path')]));
            $submission->delete();
        }

        return $papers->count();
    }

    /** $paper: author (email), track (1-based position), title_th/en, keywords_th/en, coauthors [[name, affiliation, email]]. */
    protected function samplePaper(array $paper, int $number, Carbon $at): Submission
    {
        $author = User::where('email', $paper['author'])->firstOrFail();
        $track = Track::orderBy('id')->skip($paper['track'] - 1)->firstOrFail();

        $submission = new Submission([
            'track_id' => $track->id,
            'title_th' => $paper['title_th'],
            'title_en' => $paper['title_en'],
            'abstract_th' => "บทความนี้นำเสนอ{$paper['title_th']} โดยเก็บข้อมูลจากผู้ใช้งานจริง ผลการทดลองพบว่าระบบช่วยลดเวลาและเพิ่มความพึงพอใจของผู้ใช้อย่างมีนัยสำคัญ พร้อมข้อเสนอแนะสำหรับการนำไปใช้ในหน่วยงานอื่น",
            'abstract_en' => 'This paper presents '.lcfirst($paper['title_en']).'. Data were collected from real users. The results show that the system saves time and significantly raises user satisfaction, with suggestions for other organisations.',
            'keywords_th' => $paper['keywords_th'],
            'keywords_en' => $paper['keywords_en'],
            ...$this->pdf("v1-{$number}", $paper['title_en'], static::TAG),
        ]);
        $submission->user()->associate($author);
        // Model events are off while seeding (withoutEvents covers every model), so year and code are set here
        $submission->forceFill(['year' => Submission::conferenceYear(), 'status' => SubmissionStatus::Submitted, 'review_round' => 0, 'created_at' => $at, 'updated_at' => $at])->save();
        $submission->assignCode();

        $submission->authors()->createMany([
            ['position' => 1, 'name' => $author->name, 'affiliation' => 'มหาวิทยาลัยมหาสารคาม', 'email' => $author->email],
            ...array_map(fn ($c, $i) => ['position' => $i + 2, 'name' => $c[0], 'affiliation' => $c[1], 'email' => $c[2]], $paper['coauthors'], array_keys($paper['coauthors'])),
        ]);

        // Version 1: the PDF on the paper, plus Word and the Turnitin report
        $version = $submission->addManuscript($submission->only('file_path', 'file_name', 'file_size'), 'initial', $author);
        $word = $this->docx("v{$version}-{$number}", $paper['title_en'], static::TAG);
        $turnitin = [...$this->pdf("turnitin-{$number}", 'Turnitin similarity report: 6% - '.$paper['title_en'], static::TAG), 'file_name' => 'turnitin-report.pdf'];
        foreach (['word' => $word, 'turnitin' => $turnitin] as $kind => $file) {
            $submission->files()->create([
                'version' => $version, 'kind' => $kind, 'stage' => 'initial', 'round' => 0,
                'path' => $file['file_path'], 'name' => $file['file_name'], 'size' => $file['file_size'], 'user_id' => $author->id,
            ]);
        }
        $submission->files()->update(['created_at' => $at]);

        $this->sampleEvent($submission, SubmissionAction::Submitted, $author, $at);

        return $submission;
    }

    /** History entry with its own date (Submission::log() always uses now); $from = the status before a status change. */
    protected function sampleEvent(Submission $submission, SubmissionAction $action, User $user, Carbon $when, ?SubmissionStatus $from = null, ?string $comment = null, ?array $meta = null): void
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
}
