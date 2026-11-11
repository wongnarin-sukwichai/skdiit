<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Workflow end to end: first gate (journal offer, author answer), conference track, slides (inside a rolled-back transaction). */
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
        // Only with the full demo sample (its author malee@), not the small sample:journal / sample:review papers
        if (! $paper || ! User::where('email', 'malee@skdiit.test')->exists()) {
            $this->markTestSkipped('Seed the sample papers first.');
        }
        $pages = [
            'wongnarin.s@msu.ac.th' => ['/admin', '/admin/submissions', '/admin/selection', "/admin/selection/{$paper->id}", "/admin/submissions/{$paper->id}", '/admin/settings', '/admin/staff', '/admin/reviewers', '/admin/registrants', '/admin/publication', '/admin/presentations', '/admin/reports', '/admin/activity-log', '/admin/notifications'],
            'editor@skdiit.test' => ['/admin', '/admin/submissions', "/admin/submissions/{$paper->id}"],
            'journal@skdiit.test' => ['/admin', '/admin/selection', '/admin/selection/'.Submission::where('code', 'SKDIIT27-0001')->value('id')],
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

        // Page texts (the About page was removed 2026-10-16)
        $contact = ['lead' => ['th' => 'ก', 'en' => 'A'], 'address' => ['th' => 'ข', 'en' => 'B'], 'hours' => ['th' => 'ค', 'en' => 'C']];
        $this->actingAs($admin)->put('/admin/settings/site/pages/contact', $contact)->assertSessionHasNoErrors();
        $this->actingAs($admin)->put('/admin/settings/site/pages/about', [])->assertNotFound();
        $this->get('/contact')->assertOk()->assertInertia(fn ($page) => $page->where('site.contact.lead.th', 'ก')->missing('site.about'));
        $this->get('/about')->assertNotFound();

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
        $partner = \App\Models\Journal::create(['name' => 'Mail Journal', 'url' => 'https://mail.example', 'active' => true]);
        $this->actingAs($journal)->post("/admin/selection/{$paper->id}", ['to' => 'journal', 'track_id' => $paper->track_id, 'journal_id' => $partner->id, 'comment' => 'secret reason'])->assertSessionHasNoErrors();
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
        $signup = ['email' => 'pdpa@smoke.test', 'name' => 'ทดสอบ', 'affiliation' => 'msu', 'participation' => 'attend_submit', 'meal' => 'regular'];
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

        // A reviewer sees the editor's decision on the round they evaluated (seeded sample: SKDIIT27-0011, revision requested)
        $paper = Submission::where('code', 'SKDIIT27-0011')->first();
        if (! $paper) {
            $this->markTestSkipped('Seed the sample papers first.');
        }
        $review = $paper->reviews()->whereHas('reviewer', fn ($q) => $q->where('email', 'reviewer@skdiit.test'))->first();
        $this->assertSame('review_revision', $review->outcomeForReviewer()['decision']);
        $this->actingAs($review->reviewer)->get("/admin/reviews/{$review->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('review.outcome.decision', 'review_revision'));
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
        $send = ['recommendation' => 'revise', 'comment' => 'ok', 'no_conflict' => '1'];
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}", [...$send, 'scores' => [$method->id => 4]])->assertSessionHasErrors("scores.{$writing->id}");
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}", [...$send, 'scores' => [$method->id => 4, $writing->id => 6]])->assertSessionHasErrors("scores.{$writing->id}");
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}", [...$send, 'scores' => [$method->id => 4, $writing->id => 3]])->assertSessionHasNoErrors();

        // Editors see the scores and the average; authors never do
        $editor = $this->user('editor');
        $this->actingAs($editor)->get("/admin/submissions/{$paper->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('reviews.0.average', 3.5)->has('reviews.0.scores', 2));
        $paper->forceFill(['status' => SubmissionStatus::ReviewRevision])->save(); // the round is decided: authors read the comments
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
        \Illuminate\Support\Facades\Mail::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        Setting::put('submission_deadline', now()->addDay()->format('Y-m-d H:i'));
        Setting::put('journal_answer_days', 5);
        $partner = \App\Models\Journal::create(['name' => 'Smoke Journal', 'url' => 'https://journal.example', 'terms' => 'free', 'active' => true]);
        $off = \App\Models\Journal::create(['name' => 'Off Journal', 'active' => false]);

        $journal = $this->user('journal');
        $editor = $this->user('editor');
        $admin = $this->user('admin');
        $author = $this->user('user', 'attend_submit');
        $newPaper = fn (string $title) => $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => $title, 'title_en' => $title, 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ])->fresh();
        $paper = $newPaper('ท1');
        $this->assertSame(SubmissionStatus::Submitted, $paper->status);
        $offer = ['to' => 'journal', 'track_id' => Track::first()->id, 'journal_id' => $partner->id, 'comment' => 'secret'];

        // Selection is one menu for admin, editors and the journal role; the old Journal menu is gone
        foreach ([$journal, $editor, $admin] as $user) {
            $this->actingAs($user)->get('/admin/selection')->assertOk();
        }
        $this->actingAs($journal)->get('/admin/journal')->assertNotFound();
        $this->actingAs($journal)->get("/admin/selection/{$paper->id}")->assertOk();
        $this->actingAs($journal)->get("/admin/submissions/{$paper->id}")->assertRedirect(); // no Submissions menu
        $this->actingAs($author)->get('/admin/selection')->assertRedirect();
        $this->actingAs($journal)->get('/admin')->assertOk();
        $this->actingAs($editor)->get('/admin/selection/export?tab=all')->assertOk()->assertDownload();

        // Before the submission deadline nobody routes a paper
        $this->actingAs($editor)->post("/admin/selection/{$paper->id}", $offer)->assertForbidden();
        Setting::put('submission_deadline', now()->subDay()->format('Y-m-d H:i'));

        // A switched-off journal cannot be offered; authors have no Selection module
        $this->actingAs($editor)->post("/admin/selection/{$paper->id}", [...$offer, 'journal_id' => $off->id])->assertSessionHasErrors('journal_id');
        $this->actingAs($author)->post("/admin/selection/{$paper->id}", $offer)->assertRedirect();
        $this->assertSame(SubmissionStatus::Submitted, $paper->fresh()->status);

        // The editor sets the track and offers it to a journal: the author is emailed and answers within 5 days
        $otherTrack = Track::orderByDesc('id')->first();
        $this->actingAs($editor)->post("/admin/selection/{$paper->id}", [...$offer, 'track_id' => $otherTrack->id])->assertSessionHasNoErrors();
        $paper = $paper->fresh();
        $this->assertSame(SubmissionStatus::JournalSelected, $paper->status);
        $this->assertSame($otherTrack->id, $paper->track_id);
        $this->assertSame(today()->addDays(5)->toDateString(), $paper->journal_answer_due->toDateString());
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TemplatedMail::class, fn ($mail) => $mail->hasTo($author->email)
            && str_contains($mail->bodyTh, 'Smoke Journal') && ! str_contains($mail->bodyTh, 'secret'));
        $this->assertSame(['mine' => true, 'task' => 'answerJournal', 'due' => $paper->journal_answer_due->toDateString(), 'daysLate' => 0], $paper->nextForAuthor());
        $this->actingAs($author)->get("/admin/my-submissions/{$paper->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('submission.journal.url', 'https://journal.example')->where('history.0.comment', null));
        $this->actingAs($journal)->get('/admin/selection?tab=offered')->assertOk()->assertInertia(fn ($page) => $page->where('counts.offered', fn ($n) => $n >= 1));
        // Another author cannot answer this offer
        $this->actingAs($this->user('user', 'attend_submit'))->post("/admin/my-submissions/{$paper->id}/journal-answer", ['answer' => 'accept'])->assertNotFound();

        // The author accepts: done with the conference until the slides; nobody can route it any more
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}/journal-answer", ['answer' => 'accept'])->assertSessionHas('success');
        $paper = $paper->fresh();
        $this->assertSame(SubmissionStatus::JournalAccepted, $paper->status);
        $this->assertSame('atJournal', $paper->nextForAuthor()['task']);
        $this->actingAs($editor)->post("/admin/selection/{$paper->id}", [...$offer, 'to' => 'conference'])->assertForbidden();
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}/journal-answer", ['answer' => 'decline'])->assertSessionHas('error');
        $this->assertFalse($paper->canUploadSlides());
        $this->actingAs($editor)->put("/admin/submissions/{$paper->id}/presentation", ['at' => now()->addDays(10)->format('Y-m-d\TH:i'), 'room' => 'A'])->assertRedirect();
        $this->assertTrue($paper->fresh()->canUploadSlides());
        $this->assertSame(['who' => 'author', 'task' => 'slides'], $paper->fresh()->waitingOn());

        // The journal role offers a second paper, then changes the journal while it waits; the author declines: conference
        $second = $newPaper('ท2');
        $other = \App\Models\Journal::create(['name' => 'Second Journal', 'active' => true]);
        $this->actingAs($journal)->post("/admin/selection/{$second->id}", $offer)->assertSessionHasNoErrors();
        $this->actingAs($journal)->post("/admin/selection/{$second->id}", [...$offer, 'journal_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $second->fresh()->journal_id);
        $this->actingAs($author)->post("/admin/my-submissions/{$second->id}/journal-answer", ['answer' => 'decline'])->assertSessionHas('success');
        $this->assertSame(SubmissionStatus::Screening, $second->fresh()->status);
        $this->assertTrue($second->fresh()->canBeScreened());
        $this->assertTrue(Submission::query()->gate('conference')->whereKey($second->id)->exists());
        $this->actingAs($editor)->post("/admin/submissions/{$second->id}/screening", ['decision' => 'pass'])->assertRedirect();
        $this->assertSame(SubmissionStatus::InReview, $second->fresh()->status);

        // A third paper is sent straight to the conference by admin
        $third = $newPaper('ท3');
        $this->actingAs($admin)->post("/admin/selection/{$third->id}", ['to' => 'conference', 'track_id' => Track::first()->id])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Screening, $third->fresh()->status);

        // An unanswered offer: reminded two days before, then expires to the conference track
        $fourth = $newPaper('ท4');
        $this->actingAs($editor)->post("/admin/selection/{$fourth->id}", $offer)->assertSessionHasNoErrors();
        Submission::whereKeyNot($fourth->id)->where('status', SubmissionStatus::JournalSelected)->update(['journal_reminded_at' => now()]);
        $fourth->forceFill(['journal_answer_due' => today()->addDay()])->save();
        $this->assertSame(1, Submission::remindJournalOffers());
        $this->assertSame(0, Submission::remindJournalOffers()); // once per offer
        $fourth->forceFill(['journal_answer_due' => today()->subDay()])->save();
        $this->actingAs($author)->get('/admin/my-submissions')->assertOk(); // page loads expire lapsed offers
        $this->assertSame(SubmissionStatus::Screening, $fourth->fresh()->status);
        $this->assertSame('expired', $fourth->events()->where('action', 'journal_offer_answered')->first()->meta['answer']);

        // Late papers (past the selection deadline) show on the dashboards
        $late = $newPaper('ท5');
        Setting::put('journal_deadline', now()->subDay()->toDateString());
        $this->assertTrue($late->fresh()->isPastJournalDeadline());
        $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->where('papers.journalPastDeadline', fn ($n) => $n >= 1));
        $this->actingAs($editor)->get('/admin/selection?tab=late')->assertOk()->assertInertia(fn ($page) => $page->where('counts.late', fn ($n) => $n >= 1));

        // Settings: the journals and the answer days (admin only); a journal with papers cannot be deleted
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertInertia(fn ($page) => $page->has('journalList'));
        $this->actingAs($admin)->put('/admin/settings/submission', ['max_mb' => 10, 'journal_answer_days' => 0])->assertSessionHasErrors('journal_answer_days');
        $this->actingAs($admin)->put('/admin/settings/submission', ['max_mb' => 10, 'journal_answer_days' => 10])->assertSessionHasNoErrors();
        $this->assertSame(10, Submission::journalAnswerDays());
        $this->actingAs($admin)->delete("/admin/settings/journals/{$partner->id}")->assertForbidden();
        $this->actingAs($admin)->post('/admin/settings/journals', ['name' => 'New', 'url' => 'not a url'])->assertSessionHasErrors('url');
        $this->actingAs($admin)->post('/admin/settings/journals', ['name' => 'New', 'url' => 'https://new.example'])->assertSessionHasNoErrors();
        $this->actingAs($editor)->post('/admin/settings/journals', ['name' => 'Nope'])->assertRedirect();
        $this->assertFalse(\App\Models\Journal::where('name', 'Nope')->exists());
    }

    /** Admin deletes a registrant with their papers and the papers' files; editors only view. */
    public function test_delete_registrant(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');
        $editor = $this->user('editor');
        $author = $this->user('user', 'attend_submit');
        $author->forceFill(['registered_year' => Submission::conferenceYear()])->save();
        \Illuminate\Support\Facades\Storage::put('submissions/smoke.pdf', 'pdf');
        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท', 'title_en' => 'T', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'submissions/smoke.pdf', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);

        $this->actingAs($admin)->get('/admin/registrants')->assertOk()
            ->assertInertia(fn ($page) => $page->where('registrants', fn ($rows) => collect($rows)->firstWhere('id', $author->id)['papersTotal'] === 1));
        $this->actingAs($editor)->delete("/admin/registrants/{$author->id}")->assertForbidden(); // editors only view
        $this->assertNotNull($author->fresh());

        $this->actingAs($admin)->delete("/admin/registrants/{$author->id}")->assertSessionHas('success');
        $this->assertNull($author->fresh());
        $this->assertNull(Submission::find($paper->id));
        \Illuminate\Support\Facades\Storage::assertMissing('submissions/smoke.pdf');
        // Staff accounts are not registrants
        $this->actingAs($admin)->delete("/admin/registrants/{$editor->id}")->assertNotFound();
    }
}
