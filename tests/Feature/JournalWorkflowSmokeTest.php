<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Journal workflow end to end: selection, final answer, conference track, slides (inside a rolled-back transaction). */
class JournalWorkflowSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, string $participation = 'attend'): User
    {
        return User::create(['name' => "Smoke {$role}", 'email' => uniqid($role).'@smoke.test', 'password' => 'x', 'role' => $role, 'affiliation' => 'msu', 'participation' => $participation]);
    }

    /** Every main page opens for each demo account with the seeded sample (php artisan db:seed --class=DemoSubmissionSeeder). */
    public function test_demo_pages_render(): void
    {
        $paper = Submission::where('code', 'SKDIIT27-0004')->first();
        if (! $paper) {
            $this->markTestSkipped('Seed the sample papers first.');
        }
        $pages = [
            'wongnarin.s@msu.ac.th' => ['/admin', '/admin/submissions', '/admin/journal', "/admin/journal/{$paper->id}", "/admin/submissions/{$paper->id}", '/admin/settings', '/admin/staff', '/admin/reviewers', '/admin/registrants', '/admin/publication', '/admin/presentations', '/admin/reports', '/admin/activity-log', '/admin/notifications'],
            'editor@skdiit.test' => ['/admin', '/admin/submissions', "/admin/submissions/{$paper->id}"],
            'journal@skdiit.test' => ['/admin', '/admin/journal', '/admin/journal/'.Submission::where('code', 'SKDIIT27-0001')->value('id')],
            'reviewer@skdiit.test' => ['/admin', '/admin/reviews'],
            'author@skdiit.test' => ['/admin', '/admin/my-submissions', '/admin/my-submissions/'.Submission::where('code', 'SKDIIT27-0011')->value('id'), '/admin/my-submissions/'.Submission::where('code', 'SKDIIT27-0011')->value('id').'/edit'],
            'malee@skdiit.test' => ['/admin/my-submissions/'.$paper->id],
        ];
        foreach ($pages as $email => $urls) {
            $user = User::where('email', $email)->firstOrFail();
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }
    }

    public function test_site_content(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');
        $editor = $this->user('editor');

        // Conference information: admin only, shared with every page
        $conference = ['shortName' => 'SKDIIT 2028', 'fullName' => ['th' => 'งานใหม่', 'en' => 'New'], 'date' => '2028-02-01', 'venue' => ['th' => 'มมส', 'en' => 'MSU'], 'email' => 'a@b.cd', 'phone' => '043-000-000'];
        $this->actingAs($editor)->put('/admin/settings/site/conference', $conference)->assertRedirect();
        $this->assertSame('SKDIIT 2027', \App\Support\SiteContent::all()['conference']['shortName']);
        $this->actingAs($admin)->put('/admin/settings/site/conference', [...$conference, 'email' => 'nope'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->put('/admin/settings/site/conference', $conference)->assertSessionHasNoErrors();
        $this->get('/contact')->assertOk()->assertInertia(fn ($page) => $page->where('site.conference.shortName', 'SKDIIT 2028')->where('site.contact.hours.th', 'จันทร์–ศุกร์ 08:30–16:30 น.'));

        // Page texts; the objectives list replaces the default one
        $this->actingAs($admin)->put('/admin/settings/site/pages/about', ['lead' => ['th' => 'ก', 'en' => 'A'], 'objectives' => [['th' => 'ข้อ 1', 'en' => 'One']]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put('/admin/settings/site/pages/nope', [])->assertNotFound();
        $this->get('/about')->assertOk()->assertInertia(fn ($page) => $page->has('site.about.objectives', 1)->has('topics', Track::count()));

        // Documents: upload, public download, delete
        $this->actingAs($admin)->post('/admin/settings/site/documents', ['name_th' => 'แม่แบบ', 'name_en' => 'Template', 'file' => \Illuminate\Http\UploadedFile::fake()->create('t.docx', 50)])->assertSessionHasNoErrors();
        $doc = \App\Models\SiteDocument::latest('id')->first();
        $this->get('/important')->assertOk()->assertInertia(fn ($page) => $page->where('documents.0.type', 'DOCX'));
        auth()->logout();
        $this->get("/documents/{$doc->id}")->assertOk();
        $this->actingAs($admin)->delete("/admin/settings/site/documents/{$doc->id}")->assertRedirect();
        $this->assertNull($doc->fresh());
    }

    public function test_new_conference_year_and_backup(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');
        $editor = $this->user('editor');
        $year = Submission::conferenceYear();
        $author = $this->user('user', 'attend_submit');
        $author->forceFill(['registered_year' => $year])->save();
        $finished = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'จบ', 'title_en' => 'Done', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $finished->forceFill(['status' => SubmissionStatus::JournalAccepted])->save();

        // Confirmation must match the new year; then the year moves on
        $this->actingAs($admin)->post('/admin/settings/year/rollover', ['year' => $year + 1, 'confirm' => 'x'])->assertSessionHasErrors('confirm');
        $this->actingAs($editor)->post('/admin/settings/year/rollover', ['year' => $year + 1, 'confirm' => $year + 1])->assertRedirect();
        $this->assertSame($year, Submission::conferenceYear());
        $this->actingAs($admin)->post('/admin/settings/year/rollover', ['year' => $year + 1, 'confirm' => $year + 1])->assertSessionHasNoErrors();
        $this->assertSame($year + 1, Submission::conferenceYear());
        $this->assertNull(Setting::get('submission_deadline'));
        $this->assertStringContainsString((string) ($year + 1), \App\Support\SiteContent::all()['conference']['shortName']);

        // Work lists start empty, the archive has last year's finished paper, the author still sees it
        $this->actingAs($editor)->get('/admin/submissions')->assertOk()->assertInertia(fn ($page) => $page->where('submissions.total', 0));
        $this->actingAs($admin)->get('/admin/articles')->assertOk()->assertInertia(fn ($page) => $page->where('articles', fn ($list) => collect($list)->contains('id', $finished->id)));
        $this->actingAs($author)->get('/admin/my-submissions')->assertOk()->assertInertia(fn ($page) => $page->where('submissions', fn ($list) => collect($list)->contains('id', $finished->id)));

        // A new paper is numbered in the new year
        $new = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ใหม่', 'title_en' => 'New', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $this->assertSame(sprintf('SKDIIT%02d-0001', ($year + 1) % 100), $new->fresh()->code);

        // Last year's registrant joins the new year
        $this->assertSame(0, \App\Support\Audience::query('authors')->whereKey($author->id)->count());
        $this->actingAs($author)->post('/join-year')->assertRedirect();
        $this->assertSame($year + 1, $author->fresh()->registered_year);

        // Backup: a ZIP with the database and the files (admin only)
        \Illuminate\Support\Facades\Storage::fake();
        \Illuminate\Support\Facades\Storage::put('submissions/sample.pdf', 'pdf');
        $this->actingAs($editor)->get('/admin/settings/year/backup')->assertRedirect();
        $response = $this->actingAs($admin)->get('/admin/settings/year/backup')->assertOk();
        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('files/submissions/sample.pdf'));
        $this->assertStringContainsString('CREATE TABLE `submissions`', $zip->getFromName('database.sql'));
        $zip->close();
    }

    public function test_announcements_and_workflow_emails(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        Setting::put('submission_deadline', now()->subDay()->format('Y-m-d H:i'));
        $editor = $this->user('editor');
        $journal = $this->user('journal');
        $author = $this->user('user', 'attend_submit');

        // A workflow step emails the author with the (edited) template; the journal's reason is never in it
        $this->actingAs($editor)->put('/admin/announcements/templates/journal_selected', [
            'subject_th' => 'คัดเลือก {code}', 'subject_en' => 'Selected {code}', 'body_th' => 'เรียน {name} {comment}', 'body_en' => 'Dear {name}',
        ])->assertSessionHasNoErrors();
        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท', 'title_en' => 'T', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $this->actingAs($journal)->post("/admin/journal/{$paper->id}/selection", ['decision' => 'select', 'comment' => 'secret reason']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TemplatedMail::class, fn ($mail) => $mail->hasTo($author->email)
            && str_contains($mail->subjectLine, 'คัดเลือก '.$paper->fresh()->code) && str_contains($mail->bodyTh, $author->name) && ! str_contains($mail->bodyTh, 'secret'));

        // Reviewer assignment emails the reviewer
        $paper->forceFill(['status' => SubmissionStatus::InReview, 'review_round' => 1])->save();
        $reviewer = $this->user('reviewer');
        $reviewer->tracks()->sync([$paper->track_id]);
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/reviews", ['reviewer_id' => $reviewer->id, 'due_date' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TemplatedMail::class, fn ($mail) => $mail->hasTo($reviewer->email));

        // Back to the default text
        $this->actingAs($editor)->delete('/admin/announcements/templates/journal_selected')->assertRedirect();
        $this->assertNull(\App\Models\EmailTemplate::find('journal_selected'));

        // An announcement to the reviewers with open reviews; {name} is personal; reviewers cannot use the page
        $this->actingAs($editor)->get('/admin/announcements')->assertOk()->assertInertia(fn ($page) => $page->where('counts.reviewers_open', fn ($n) => $n >= 1));
        $this->actingAs($editor)->post('/admin/announcements', ['audience' => 'reviewers_open', 'subject' => 'เตือน', 'body' => 'เรียน {name}'])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TemplatedMail::class, fn ($mail) => $mail->hasTo($reviewer->email) && $mail->bodyTh === "เรียน {$reviewer->name}");
        $this->assertSame(\App\Support\Audience::query('reviewers_open')->count(), \App\Models\Announcement::latest('id')->value('recipients'));
        $this->actingAs($reviewer)->get('/admin/announcements')->assertRedirect();
    }

    public function test_schedule_and_pdpa(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');

        // Important dates and the programme live in Settings (saved in date order); the old Schedule menu is gone
        $schedule = [
            'dates' => [['date' => '2027-01-28', 'label' => ['th' => 'วันงาน', 'en' => 'Day']], ['date' => '2026-12-01', 'label' => ['th' => 'ปิดรับ', 'en' => 'Deadline']]],
            'program' => [['time' => '09:00', 'label' => ['th' => 'เปิด', 'en' => 'Open']]],
        ];
        $this->actingAs($admin)->put('/admin/settings/site/schedule', [...$schedule, 'dates' => [['date' => 'x', 'label' => ['th' => '', 'en' => '']]]])->assertSessionHasErrors(['dates.0.date', 'dates.0.label.th']);
        $this->actingAs($admin)->put('/admin/settings/site/schedule', $schedule)->assertSessionHasNoErrors();
        $this->get('/schedule')->assertOk()->assertInertia(fn ($page) => $page->where('site.dates.0.date', '2026-12-01')->has('site.program', 1));
        $this->actingAs($admin)->get('/admin/schedule')->assertNotFound();

        // PDPA: no registration without consent; the time of consent is kept
        auth()->logout();
        $signup = ['email' => 'pdpa@smoke.test', 'name' => 'ทดสอบ', 'affiliation' => 'msu', 'participation' => 'attend'];
        $this->post('/register', $signup)->assertSessionHasErrors('pdpa');
        $this->post('/register', [...$signup, 'pdpa' => '1'])->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'pdpa@smoke.test')->value('pdpa_accepted_at'));
        $this->get('/privacy')->assertOk()->assertInertia(fn ($page) => $page->component('Front/Privacy'));
    }

    public function test_my_details_and_reviewer_outcome(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        // An attendee (no backend) fixes their own details from the public site; the email stays
        $attendee = $this->user('user');
        $this->actingAs($attendee)->put('/my-details', ['name' => 'ชื่อใหม่', 'phone' => '081-000-0000', 'affiliation' => 'other', 'affiliation_other' => 'โรงเรียนบ้านโนน', 'email' => 'x@y.z'])
            ->assertSessionHasNoErrors();
        $attendee->refresh();
        $this->assertSame(['ชื่อใหม่', '081-000-0000', 'other', 'โรงเรียนบ้านโนน'], [$attendee->name, $attendee->phone, $attendee->affiliation, $attendee->affiliation_other]);
        $this->assertStringEndsWith('@smoke.test', $attendee->email);
        $this->actingAs($attendee)->put('/my-details', ['name' => '', 'affiliation' => 'msu'])->assertSessionHasErrors('name');

        // A reviewer sees the editor's decision on the round they evaluated (seeded sample: SKDIIT27-0011, minor revision)
        $paper = Submission::where('code', 'SKDIIT27-0011')->first();
        if (! $paper) {
            $this->markTestSkipped('Seed the sample papers first.');
        }
        $review = $paper->reviews()->whereHas('reviewer', fn ($q) => $q->where('email', 'reviewer@skdiit.test'))->first();
        $this->assertSame('revision_minor', $review->outcomeForReviewer()['decision']);
        $this->actingAs($review->reviewer)->get("/admin/reviews/{$review->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('review.outcome.decision', 'revision_minor'));
        // Not decided yet (SKDIIT27-0009): no outcome
        $open = Submission::where('code', 'SKDIIT27-0009')->first()->reviews()->whereNotNull('submitted_at')->first();
        $this->assertNull($open->outcomeForReviewer());
    }

    public function test_review_scores(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');
        \App\Models\ReviewScore::query()->delete();
        \App\Models\ReviewCriterion::query()->delete();

        // Admin sets two criteria
        $this->actingAs($admin)->post('/admin/settings/review-criteria', ['name_th' => 'วิธีวิจัย', 'name_en' => 'Method'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/settings/review-criteria', ['name_th' => 'การเขียน', 'name_en' => 'Writing', 'description_th' => 'ความชัดเจน'])->assertSessionHasNoErrors();
        [$method, $writing] = \App\Models\ReviewCriterion::ordered()->get()->all();

        $author = $this->user('user', 'attend_submit');
        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท', 'title_en' => 'T', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $paper->forceFill(['status' => SubmissionStatus::InReview, 'review_round' => 1])->save();
        $reviewer = $this->user('reviewer');
        $review = $paper->reviews()->create(['reviewer_id' => $reviewer->id, 'round' => 1, 'due_date' => now()->addWeek()]);

        // A draft may be partial; sending needs every criterion
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}/draft", ['scores' => [$method->id => 4]])->assertSessionHasNoErrors();
        $this->assertSame(1, $review->scores()->count());
        $this->actingAs($reviewer)->get("/admin/reviews/{$review->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->has('criteria', 2)->where('review.scores.0.score', 4));
        $send = ['recommendation' => 'minor', 'comment' => 'ok', 'no_conflict' => '1'];
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}", [...$send, 'scores' => [$method->id => 4]])->assertSessionHasErrors("scores.{$writing->id}");
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}", [...$send, 'scores' => [$method->id => 4, $writing->id => 6]])->assertSessionHasErrors("scores.{$writing->id}");
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}", [...$send, 'scores' => [$method->id => 4, $writing->id => 3]])->assertSessionHasNoErrors();

        // Editors see the scores and the average; authors never do
        $editor = $this->user('editor');
        $this->actingAs($editor)->get("/admin/submissions/{$paper->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('reviews.0.average', 3.5)->has('reviews.0.scores', 2));
        $paper->forceFill(['status' => SubmissionStatus::RevisionMinor])->save(); // the round is decided: authors read the comments
        $forAuthor = $paper->fresh()->reviewsForAuthor()[0]['reviews'][0];
        $this->assertSame('ok', $forAuthor['comment']);
        $this->assertArrayNotHasKey('scores', $forAuthor);

        // A scored criterion cannot be deleted, an unused one can
        $this->actingAs($admin)->delete("/admin/settings/review-criteria/{$method->id}")->assertForbidden();
        $unused = \App\Models\ReviewCriterion::create(['name_th' => 'x', 'name_en' => 'x', 'sort_order' => 9]);
        $this->actingAs($admin)->delete("/admin/settings/review-criteria/{$unused->id}")->assertRedirect();
        $this->assertNull($unused->fresh());
    }

    public function test_three_required_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        Setting::put('submission_opens', null);
        Setting::put('submission_deadline', now()->addDays(5)->format('Y-m-d H:i'));
        $author = $this->user('user', 'attend_submit');
        $pdf = fn (string $name = 'paper.pdf') => \Illuminate\Http\UploadedFile::fake()->create($name, 100, 'application/pdf');
        $word = fn () => \Illuminate\Http\UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $fields = [
            'track_id' => Track::first()->id, 'title_th' => 'ท', 'title_en' => 'T', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'authors' => [['name' => 'A', 'affiliation' => 'MSU']],
        ];

        // All three files are required for a new paper; a Word file is not accepted as the PDF
        $this->actingAs($author)->post('/admin/my-submissions', $fields)->assertSessionHasErrors(['file', 'word_file', 'turnitin_file']);
        $this->actingAs($author)->post('/admin/my-submissions', [...$fields, 'file' => $word(), 'word_file' => $word(), 'turnitin_file' => $pdf()])->assertSessionHasErrors('file');
        $this->actingAs($author)->post('/admin/my-submissions', [
            ...$fields, 'file' => $pdf(), 'word_file' => $word(), 'turnitin_file' => $pdf('turnitin.pdf'), 'supplementary_files' => [$pdf('data.pdf')],
        ])->assertSessionHasNoErrors();
        $paper = $author->submissions()->latest('id')->first();
        $files = $paper->currentFiles();
        $this->assertSame('turnitin.pdf', $files['turnitin']['name']);
        $this->assertCount(1, $files['supplementary']);

        // Editing before the deadline: nothing required; a new Word file joins the current version
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}", [...$fields, '_method' => 'put', 'word_file' => $word()])->assertSessionHasNoErrors();
        $this->assertSame(1, $paper->currentVersion());

        // Revision: PDF and Word required, Turnitin optional (the old report stays)
        $paper->forceFill(['status' => SubmissionStatus::Revision])->save();
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}", [...$fields, '_method' => 'put', 'file' => $pdf()])->assertSessionHasErrors('word_file');
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}", [...$fields, '_method' => 'put', 'file' => $pdf('v2.pdf'), 'word_file' => $word()])->assertSessionHasNoErrors();
        $paper->refresh();
        $this->assertSame(SubmissionStatus::Screening, $paper->status);
        $this->assertSame(2, $paper->currentVersion());
        $this->assertSame('v2.pdf', $paper->currentFiles()['pdf']['name']);
        $this->assertSame('turnitin.pdf', $paper->currentFiles()['turnitin']['name']);

        // A reviewer gets the PDF but not the Word, Turnitin or supplementary files
        $reviewer = $this->user('reviewer');
        $paper->reviews()->create(['reviewer_id' => $reviewer->id, 'round' => 1, 'due_date' => now()->addWeek()]);
        $byKind = fn (string $kind) => $paper->files()->where('kind', $kind)->latest('id')->first();
        $this->actingAs($reviewer)->get("/admin/submission-files/{$byKind('manuscript')->id}")->assertOk();
        foreach (['word', 'turnitin', 'supplementary'] as $kind) {
            $this->actingAs($reviewer)->get("/admin/submission-files/{$byKind($kind)->id}")->assertNotFound();
        }
        $this->assertSame([], $paper->versionsFor('reviewer')[0]['word']);
        $this->actingAs($author)->get("/admin/submission-files/{$byKind('turnitin')->id}")->assertOk();
    }

    public function test_journal_flow(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        Setting::put('submission_deadline', now()->subDay()->format('Y-m-d H:i'));
        Setting::put('journal_url', 'https://journal.example');

        $journal = $this->user('journal');
        $editor = $this->user('editor');
        $admin = $this->user('admin');
        $author = $this->user('user', 'attend_submit');

        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท', 'title_en' => 'T', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $paper->refresh();
        $this->assertSame(SubmissionStatus::Submitted, $paper->status);

        // Journal list and page; editors have no journal menu
        $this->actingAs($journal)->get('/admin/journal')->assertOk();
        $this->actingAs($journal)->get("/admin/journal/{$paper->id}")->assertOk();
        $this->actingAs($editor)->get('/admin/journal')->assertRedirect();
        $this->actingAs($editor)->get("/admin/submissions/{$paper->id}")->assertOk();
        $this->actingAs($journal)->get('/admin')->assertOk();

        // Editors cannot select for the journal
        $this->actingAs($editor)->post("/admin/journal/{$paper->id}/selection", ['decision' => 'select'])->assertRedirect();
        $this->assertSame(SubmissionStatus::Submitted, $paper->fresh()->status);

        // Select, then the journal rejects: conference track (editor screening)
        $this->actingAs($journal)->post("/admin/journal/{$paper->id}/selection", ['decision' => 'select', 'comment' => 'secret'])->assertRedirect();
        $this->assertSame(SubmissionStatus::JournalSelected, $paper->fresh()->status);
        $this->actingAs($author)->get("/admin/my-submissions/{$paper->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('journalUrl', 'https://journal.example')->where('history.0.comment', null));
        $this->actingAs($journal)->post("/admin/journal/{$paper->id}/result", ['result' => 'rejected'])->assertRedirect();
        $this->assertSame(SubmissionStatus::Screening, $paper->fresh()->status);
        $this->assertTrue($paper->fresh()->canBeScreened());

        // Hidden from the journal now
        $this->actingAs($journal)->get("/admin/journal/{$paper->id}")->assertNotFound();
        $this->actingAs($journal)->get("/admin/submissions/{$paper->id}/file")->assertNotFound();

        // Screening revision goes back to screening (not to the journal)
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/screening", ['decision' => 'pass'])->assertRedirect();
        $this->assertSame(SubmissionStatus::InReview, $paper->fresh()->status);

        // Admin moves a waiting paper past the journal deadline to the conference track
        $other = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท2', 'title_en' => 'T2', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        Setting::put('journal_deadline', now()->subDay()->toDateString());
        $this->assertTrue($other->fresh()->isPastJournalDeadline());
        $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->where('papers.journalPastDeadline', fn ($n) => $n >= 1));
        $this->actingAs($admin)->post("/admin/journal/{$other->id}/selection", ['decision' => 'decline'])->assertRedirect();
        $this->assertSame(SubmissionStatus::Screening, $other->fresh()->status);

        // Journal accepts a third paper: presents, slides open only with a slot
        $third = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท3', 'title_en' => 'T3', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $this->actingAs($journal)->post("/admin/journal/{$third->id}/selection", ['decision' => 'select']);
        $this->actingAs($journal)->post("/admin/journal/{$third->id}/result", ['result' => 'accepted']);
        $third = $third->fresh();
        $this->assertSame(SubmissionStatus::JournalAccepted, $third->status);
        $this->assertFalse($third->canUploadSlides());
        $this->assertSame('schedule', $third->waitingOn()['task']);
        $this->actingAs($editor)->put("/admin/submissions/{$third->id}/presentation", ['at' => now()->addDays(10)->format('Y-m-d\TH:i'), 'room' => 'A'])->assertRedirect();
        $third = $third->fresh();
        $this->assertTrue($third->canUploadSlides());
        $this->assertSame(['who' => 'author', 'task' => 'slides'], $third->waitingOn());
        $this->actingAs($author)->get('/admin/my-submissions')->assertOk();
        $this->actingAs($editor)->get('/admin/submissions')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->put('/admin/settings/submission', ['max_mb' => 10, 'journal_url' => 'not a url'])->assertSessionHasErrors('journal_url');
    }
}
