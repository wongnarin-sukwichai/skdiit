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
            'wongnarin.s@msu.ac.th' => ['/admin', '/admin/submissions', '/admin/selection', "/admin/selection/{$paper->id}", "/admin/submissions/{$paper->id}", '/admin/settings', '/admin/staff', '/admin/reviewers', '/admin/registrants', '/admin/presentations', '/admin/reports', '/admin/activity-log', '/admin/notifications'],
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
        $reviewer = $this->user('reviewer');
        $oldReview = $finished->reviews()->create(['reviewer_id' => $reviewer->id, 'round' => 1, 'due_date' => now()->addWeek()]);
        $this->actingAs($reviewer)->get("/admin/reviews/{$oldReview->id}")->assertOk();

        // Confirmation must match the new year; then the year moves on
        $this->actingAs($admin)->post('/admin/settings/year/rollover', ['year' => $year + 1, 'confirm' => 'x'])->assertSessionHasErrors('confirm');
        $this->actingAs($editor)->post('/admin/settings/year/rollover', ['year' => $year + 1, 'confirm' => $year + 1])->assertRedirect();
        $this->assertSame($year, Submission::conferenceYear());
        $this->actingAs($admin)->post('/admin/settings/year/rollover', ['year' => $year + 1, 'confirm' => $year + 1, 'suspend_reviewers' => true])->assertSessionHasNoErrors();
        $this->assertSame($year + 1, Submission::conferenceYear());

        // Reviewers: every account suspended (admin re-opens those who continue); last year's work is closed to them
        $this->assertNotNull($reviewer->fresh()->suspended_at);
        $reviewer->forceFill(['suspended_at' => null])->save();
        $this->actingAs($reviewer)->get('/admin/reviews')->assertOk()->assertInertia(fn ($page) => $page->where('reviews', []));
        $this->actingAs($reviewer)->get("/admin/reviews/{$oldReview->id}")->assertNotFound();
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
        $this->assertSame(0, \App\Support\Audience::query('registrants')->whereKey($author->id)->count());
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
        $admin = $this->user('admin');
        $journal = $this->user('journal');
        $author = $this->user('user', 'attend_submit');

        // A workflow step emails the author with the (edited) template; the journal's reason is never in it
        $this->actingAs($admin)->put('/admin/announcements/templates/journal_selected', [
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
        // The Submissions list shows this round's reviewers at a glance
        $this->actingAs($editor)->get("/admin/submissions?q={$paper->code}")->assertInertia(fn ($page) => $page
            ->where('submissions.data.0.reviewers.0.name', $reviewer->name)->where('submissions.data.0.reviewers.0.state', 'pending'));

        // Back to the default text
        $this->actingAs($admin)->delete('/admin/announcements/templates/journal_selected')->assertRedirect();
        $this->assertNull(\App\Models\EmailTemplate::find('journal_selected'));

        // An announcement to the reviewers with open reviews; {name} is personal. Editors send to the to-do groups
        // only; the broad groups (everyone) are admin's (2026-11-13)
        $this->actingAs($editor)->get('/admin/announcements')->assertOk()
            ->assertInertia(fn ($page) => $page->where('allowed', fn ($keys) => ! collect($keys)->contains('registrants') && collect($keys)->contains('reviewers_open')));
        $this->actingAs($editor)->post('/admin/announcements', ['audience' => 'registrants', 'subject' => 'ทุกคน', 'body' => 'x'])->assertSessionHasErrors('audience');
        $this->actingAs($admin)->get('/admin/announcements')->assertOk()->assertInertia(fn ($page) => $page->where('counts.reviewers_open', fn ($n) => $n >= 1)->where('allowed', fn ($keys) => collect($keys)->contains('registrants')));
        $this->actingAs($editor)->post('/admin/announcements', ['audience' => 'reviewers_open', 'subject' => 'เตือน', 'body' => 'เรียน {name}'])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TemplatedMail::class, fn ($mail) => $mail->hasTo($reviewer->email) && $mail->bodyTh === "เรียน {$reviewer->name}");
        $this->assertSame(\App\Support\Audience::query('reviewers_open')->count(), \App\Models\Announcement::latest('id')->value('recipients'));
        $this->actingAs($reviewer)->get('/admin/announcements')->assertRedirect();

        // Email log: every send is recorded; editors see it (no password emails) and may send one again
        $log = \App\Models\EmailLog::where('kind', 'announcement')->where('to_email', $reviewer->email)->latest('id')->firstOrFail();
        $this->assertSame('sent', $log->status);
        $this->actingAs($editor)->get('/admin/notifications?tab=emails&q='.urlencode($reviewer->email))->assertOk()
            ->assertInertia(fn ($page) => $page->where('emails.data.0.id', $log->id));
        $this->actingAs($editor)->post("/admin/notifications/emails/{$log->id}/resend")->assertSessionHas('success');
        $this->assertSame(1, $log->fresh()->resent);
        $password = \App\Models\EmailLog::create(['user_id' => $reviewer->id, 'to_email' => $reviewer->email, 'kind' => 'password_reset', 'subject' => 'x', 'status' => 'sent']);
        $this->actingAs($editor)->post("/admin/notifications/emails/{$password->id}/resend")->assertForbidden();
    }

    public function test_schedule_and_pdpa(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');

        // Activity log: a settings change and a failed sign-in are recorded; admin reads them, editors do not
        $this->actingAs($admin)->put('/admin/settings/schedule/notify', ['start' => '2027-01-05', 'end' => '2027-01-05', 'mode' => 'info'])->assertSessionHasNoErrors();
        $this->assertTrue(\App\Models\ActivityLog::where('user_id', $admin->id)->where('action', 'settings_changed')->where('detail', 'settings')->exists());
        auth()->logout();
        $this->post('/login', ['email' => 'nobody@smoke.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertTrue(\App\Models\ActivityLog::where('action', 'login_failed')->where('target', 'nobody@smoke.test')->where('flagged', true)->exists());
        $this->actingAs($admin)->get('/admin/activity-log?category=auth&q=nobody@smoke.test')->assertOk()
            ->assertInertia(fn ($page) => $page->where('entries.data.0.action', 'login_failed')->where('entries.data.0.flagged', true));
        $this->actingAs($this->user('editor'))->get('/admin/activity-log?q=nobody@smoke.test')->assertOk()
            ->assertInertia(fn ($page) => $page->where('entries.total', 0));
        $this->actingAs($admin)->getJson('/admin/activity-log/rows?category=system')->assertOk()->assertJsonPath('0.action', 'settings_changed');

        // Timeline rows not enforced yet are saved too (App\Support\SchedulePlan) and shared with backend pages
        $this->actingAs($admin)->put('/admin/settings/schedule/notify', ['start' => '2027-01-06', 'end' => '2027-01-06', 'mode' => 'info', 'public' => true])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put('/admin/settings/schedule/review', ['start' => '2026-12-20', 'end' => '2026-12-10', 'mode' => 'warn'])->assertSessionHasErrors('end');
        $this->actingAs($admin)->put('/admin/settings/schedule/submission', ['start' => '2026-12-20', 'end' => '2026-12-21', 'mode' => 'warn'])->assertNotFound();
        $this->actingAs($this->user('editor'))->put('/admin/settings/schedule/notify', ['start' => '2027-02-01', 'end' => '2027-02-01', 'mode' => 'info']);
        $this->assertSame('2027-01-06', \App\Support\SchedulePlan::all()['notify']['start']);
        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn ($page) => $page->where('schedulePlan.notify.start', '2027-01-06'));
        // The editor home lays its work along that schedule (EditorTimeline)
        $this->actingAs($this->user('editor'))->get('/admin')->assertOk()
            ->assertInertia(fn ($page) => $page->has('editorStages.stages.selection')->has('editorStages.queues.screening')->has('editorStages.schedule.deadline')->where('schedulePlan.notify.start', '2027-01-06'));

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
        // Registration row (lock mode): no sign-up outside its dates, whatever the real schedule says
        \App\Support\SchedulePlan::put('registration', ['start' => now()->subDays(10)->toDateString(), 'end' => now()->subDay()->toDateString(), 'mode' => 'lock', 'public' => true]);
        $this->post('/register', [...$signup, 'pdpa' => '1'])->assertSessionHasErrors(['email' => 'auth.registrationClosed']);
        \App\Support\SchedulePlan::put('registration', ['start' => now()->subDay()->toDateString(), 'end' => now()->addDay()->toDateString(), 'mode' => 'lock', 'public' => true]);
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
        $editor = $this->user('editor');
        $this->actingAs($reviewer)->post("/admin/reviews/{$review->id}", [...$send, 'scores' => [$method->id => 4, $writing->id => 3]])->assertSessionHasNoErrors();

        // Notifications (bell): the editors hear about the review, the reviewer does not hear about their own action
        $notice = \App\Models\UserNotification::where('user_id', $editor->id)->where('kind', 'review_submitted')->latest('id')->firstOrFail();
        $this->assertSame($paper->code, $notice->params['code']);
        $this->assertFalse(\App\Models\UserNotification::where('user_id', $reviewer->id)->where('kind', 'review_submitted')->exists());
        $this->actingAs($editor)->get('/admin')->assertInertia(fn ($page) => $page->where('notifications.items.0.kind', 'review_submitted'));
        $this->actingAs($reviewer)->postJson("/admin/notifications/{$notice->id}/read")->assertForbidden();
        $this->actingAs($editor)->postJson("/admin/notifications/{$notice->id}/read")->assertOk();
        $this->assertNotNull($notice->fresh()->read_at);

        // Reports and the admin home are real: the sent review and its scores are counted
        $this->actingAs($this->user('admin'))->get('/admin/reports')->assertOk()
            ->assertInertia(fn ($page) => $page->where('report.reviews.kpis.done', fn ($n) => $n >= 1)->has('report.reviews.criteria')->has('report.papers.byJournal')->has('report.registrants.meals'));
        $this->actingAs($this->user('admin'))->get('/admin')->assertOk()
            ->assertInertia(fn ($page) => $page->has('papers.people.trend', 14)->has('papers.failedEmails')->has('papers.noSession'));

        // Editors see the scores and the average; authors never do
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

        // PDF and Word are required for a new paper, Turnitin is optional (2026-11-12); a Word file is not accepted as the PDF
        $this->actingAs($author)->post('/admin/my-submissions', $fields)->assertSessionHasErrors(['file', 'word_file'])->assertSessionDoesntHaveErrors('turnitin_file');
        $this->actingAs($author)->post('/admin/my-submissions', [...$fields, 'file' => $word(), 'word_file' => $word(), 'turnitin_file' => $pdf()])->assertSessionHasErrors('file');

        // English only, no track and no Turnitin report: accepted; the editor sets the track at the first gate
        $this->actingAs($author)->post('/admin/my-submissions', [
            'title_en' => 'English only', 'abstract_en' => 'a', 'keywords_en' => 'k', 'authors' => [['name' => 'A', 'affiliation' => 'MSU']],
            'file' => $pdf(), 'word_file' => $word(),
        ])->assertSessionHasNoErrors();
        $englishOnly = $author->submissions()->latest('id')->first();
        $this->assertNull($englishOnly->track_id);
        $this->assertNull($englishOnly->title_th);
        $this->actingAs($author)->get("/admin/my-submissions/{$englishOnly->id}")->assertOk();

        // Editors set the track on Selection before submissions close; the journal role and authors cannot
        $this->actingAs($this->user('editor'))->get('/admin/selection')->assertOk();
        $this->actingAs($this->user('journal'))->put("/admin/selection/{$englishOnly->id}/track", ['track_id' => Track::first()->id])->assertForbidden();
        $this->actingAs($author)->put("/admin/selection/{$englishOnly->id}/track", ['track_id' => Track::first()->id]); // no Selection module
        $this->assertNull($englishOnly->fresh()->track_id);
        $this->actingAs($this->user('editor'))->put("/admin/selection/{$englishOnly->id}/track", ['track_id' => Track::first()->id])->assertSessionHasNoErrors();
        $this->assertSame(Track::first()->id, $englishOnly->fresh()->track_id);
        $this->assertTrue($englishOnly->events()->where('action', 'track_changed')->exists());

        // Submissions lists the conference track only: a first-gate paper is not there, a search points to Selection
        $listEditor = $this->user('editor');
        $this->actingAs($listEditor)->get('/admin/submissions?q=English+only')
            ->assertInertia(fn ($page) => $page->where('submissions.total', 0)->where('atFirstGate', 1)->where('statuses', fn ($s) => ! collect($s)->contains('submitted')));

        // The editor may attach a file to a screening decision; the author downloads it and sees it on the revision form
        $englishOnly->forceFill(['status' => \App\Enums\SubmissionStatus::Screening])->save();
        $this->actingAs($listEditor)->get('/admin/submissions?q=English+only')->assertInertia(fn ($page) => $page->where('submissions.total', 1)->where('atFirstGate', 0));
        // Tabs by stage: a new paper to screen is in "screening"; old ?queue= links open the matching tab
        $this->actingAs($listEditor)->get('/admin/submissions?stage=screening&q=English+only')->assertInertia(fn ($page) => $page->where('submissions.total', 1)->has('stages.reviewing.count'));
        $this->actingAs($listEditor)->get('/admin/submissions?stage=revisedScreening&q=English+only')->assertInertia(fn ($page) => $page->where('submissions.total', 0));
        $this->actingAs($listEditor)->get('/admin/submissions?queue=needReviewers')->assertInertia(fn ($page) => $page->where('filters.stage', 'findReviewers'));
        $this->actingAs($this->user('editor'))->post("/admin/submissions/{$englishOnly->id}/screening", [
            'decision' => 'revise', 'comment' => 'See the marked-up PDF', 'attachment' => $pdf('marked-up.pdf'),
        ])->assertSessionHasNoErrors();
        $file = $englishOnly->events()->where('action', 'screened')->latest('id')->first()->meta['file'];
        $this->assertSame('marked-up.pdf', $file['name']);
        $this->actingAs($author)->get($file['url'])->assertOk();
        $this->actingAs($author)->get("/admin/my-submissions/{$englishOnly->id}/edit")
            ->assertInertia(fn ($page) => $page->where('submission.feedbackFile.name', 'marked-up.pdf'));
        $englishOnly->delete();
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
        // Editors put it in a presentation session (2026-11-12): its slot follows from the session and the running order
        $room = \App\Models\PresentationRoom::create(['name_th' => 'ห้อง A', 'name_en' => 'Room A']);
        $session = \App\Models\PresentationSession::create(['year' => Submission::conferenceYear(), 'date' => now()->addDays(10)->toDateString(), 'start_time' => '09:00', 'end_time' => '09:30', 'presentation_room_id' => $room->id, 'minutes' => 15]);
        $this->actingAs($editor)->put("/admin/submissions/{$paper->id}/presentation", ['session_id' => $session->id])->assertSessionHasNoErrors();
        $this->assertSame('09:00', $paper->fresh()->presentation_at->format('H:i'));
        $this->assertSame('ห้อง A', $paper->fresh()->presentation_room);
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

        // An unanswered offer (option ข2, 2026-11-13): nothing happens on its own. Editors remind with a button;
        // past the answer date it only turns red, and stays until an editor moves it (a late answer still counts)
        $fourth = $newPaper('ท4');
        $this->actingAs($editor)->post("/admin/selection/{$fourth->id}", $offer)->assertSessionHasNoErrors();
        $this->actingAs($editor)->post('/admin/selection/remind-offers')->assertSessionHas('success');
        $this->assertNotNull($fourth->fresh()->journal_reminded_at);
        $this->assertTrue($fourth->events()->where('action', 'journal_offer_reminded')->where('user_id', $editor->id)->exists());
        $this->actingAs($journal)->post('/admin/selection/remind-offers')->assertSessionHas('success'); // the journal role has full Selection too
        $fourth->forceFill(['journal_answer_due' => today()->subDay()])->save();
        $this->actingAs($author)->get('/admin/my-submissions')->assertOk();
        $this->actingAs($editor)->get('/admin/selection?tab=offered')->assertOk()
            ->assertInertia(fn ($page) => $page->where('pendingOffers.overdue', fn ($n) => $n >= 1));
        $this->assertSame(SubmissionStatus::JournalSelected, $fourth->fresh()->status);
        $this->assertTrue($fourth->fresh()->journalOfferOverdue());
        $this->actingAs($editor)->post("/admin/selection/{$fourth->id}", ['to' => 'conference', 'track_id' => Track::first()->id])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Screening, $fourth->fresh()->status);

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

    /** A reviewer's notes file reaches the author once the round is decided, renamed; editors may hold it back. */
    public function test_reviewer_file_to_author(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $editor = $this->user('editor');
        $reviewer = $this->user('reviewer');
        $author = $this->user('user', 'attend_submit');
        \Illuminate\Support\Facades\Storage::put('submissions/smoke.pdf', 'pdf');
        \Illuminate\Support\Facades\Storage::put('reviews/notes.pdf', 'notes');
        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท', 'title_en' => 'T', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'submissions/smoke.pdf', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $paper->forceFill(['status' => SubmissionStatus::InReview, 'review_round' => 1])->save();
        $review = $paper->reviews()->create(['reviewer_id' => $reviewer->id, 'round' => 1, 'due_date' => now()->addWeek()]);
        $review->forceFill(['submitted_at' => now(), 'recommendation' => 'revise', 'attachment_path' => 'reviews/notes.pdf', 'attachment_name' => 'Dr Somchai notes.pdf', 'attachment_size' => 5])->save();

        // Round not decided yet: the author cannot have it; the reviewer and the editors can
        $this->actingAs($author)->get("/admin/review-files/{$review->id}")->assertNotFound();
        $this->actingAs($editor)->get("/admin/review-files/{$review->id}")->assertOk();

        // Decided: the author downloads it as "Reviewer 1's notes", and it sits next to the answer on the revision form
        $paper->forceFill(['status' => SubmissionStatus::ReviewRevision])->save();
        $download = $this->actingAs($author)->get("/admin/review-files/{$review->id}")->assertOk();
        $this->assertStringContainsString(rawurlencode('ไฟล์โน้ตของผู้ประเมินคนที่ 1.pdf'), $download->headers->get('content-disposition'));
        $this->assertSame('ไฟล์โน้ตของผู้ประเมินคนที่ 1.pdf', $paper->reviewsToAnswer()[0]['file']['name']);

        // Held back by an editor: gone for the author
        $this->actingAs($author)->put("/admin/review-files/{$review->id}/to-author", ['to_author' => false]);
        $this->assertTrue($review->fresh()->attachment_to_author);
        $this->actingAs($editor)->put("/admin/review-files/{$review->id}/to-author", ['to_author' => false])->assertSessionHasNoErrors();
        $this->actingAs($author)->get("/admin/review-files/{$review->id}")->assertNotFound();
        $this->assertNull($paper->fresh()->reviewsForAuthor()[0]['reviews'][0]['file']);
    }

    /** Camera-ready check (step 3 of 3): fixes go back to the author, approval locks the file; acceptance can be withdrawn. */
    public function test_camera_ready_check(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $editor = $this->user('editor');
        $author = $this->user('user', 'attend_submit');
        \Illuminate\Support\Facades\Storage::put('submissions/smoke.pdf', 'pdf');
        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ท', 'title_en' => 'T', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'submissions/smoke.pdf', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $paper->forceFill(['status' => SubmissionStatus::Accepted, 'review_round' => 1])->save();
        $pdf = fn () => \Illuminate\Http\UploadedFile::fake()->create('final.pdf', 50, 'application/pdf');
        $inTab = fn (string $stage) => Submission::currentYear()->stage($stage)->whereKey($paper->id)->exists();

        // Accepted: in "awaiting camera-ready"; the author uploads; still there, now waiting for the editors' check
        $this->assertTrue($inTab('awaitingFinal'));
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}/camera-ready", ['file' => $pdf()])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::CameraReady, $paper->fresh()->status);
        $this->assertTrue($inTab('awaitingFinal'));
        $this->assertSame(1, Submission::stageCounts()['awaitingFinal']['count'] >= 1 ? 1 : 0);

        // Fixes: a message is required; back to the author (accepted), who uploads again
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/camera-ready/fix", [])->assertSessionHasErrors('comment');
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/camera-ready/fix", ['comment' => 'Use the template'])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Accepted, $paper->fresh()->status);
        $this->assertTrue($paper->fresh()->toClient()['cameraReadyFix']);
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}/camera-ready", ['file' => $pdf()])->assertSessionHasNoErrors();

        // Approved: in "camera-ready", in the proceedings ZIP count, and the author can no longer replace it
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/camera-ready/approve", [])->assertSessionHasNoErrors();
        $this->assertNotNull($paper->fresh()->camera_ready_approved_at);
        $this->assertTrue($inTab('cameraReady'));
        $this->actingAs($author)->post("/admin/my-submissions/{$paper->id}/camera-ready", ['file' => $pdf()])->assertForbidden();
        $this->actingAs($editor)->get('/admin/submissions')->assertInertia(fn ($page) => $page->where('cameraReadyCount', fn ($n) => $n >= 1));
        $this->assertTrue($paper->events()->where('action', 'camera_ready_approved')->exists());

        // Withdrawing the acceptance needs a reason; the paper ends as rejected
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/cancel-acceptance", [])->assertSessionHasErrors('comment');
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/cancel-acceptance", ['comment' => 'Plagiarism found'])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::ReviewRejected, $paper->fresh()->status);
        $this->assertNull($paper->fresh()->camera_ready_approved_at);
    }

    /** Presentation sessions: admin builds them; papers get their time from the running order; full and clashing are refused. */
    public function test_presentation_sessions(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');
        $editor = $this->user('editor');
        $day = now()->addDays(20)->toDateString();
        \App\Support\SiteContent::put('conference', [...\App\Support\SiteContent::all()['conference'], 'date' => $day, 'endDate' => now()->addDays(21)->toDateString()]);

        $paper = fn (string $code) => tap($this->user('user', 'attend_submit')->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => $code, 'title_en' => $code, 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'submissions/x.pdf', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]))->forceFill(['status' => SubmissionStatus::CameraReady, 'camera_ready_approved_at' => now()]);
        [$a, $b, $c] = [$paper('A'), $paper('B'), $paper('C')];
        foreach ([$a, $b, $c] as $p) {
            $p->save();
        }

        // Rooms and sessions are admin's; editors only view the page
        $this->actingAs($editor)->post('/admin/presentations/rooms', ['name_th' => 'ห้อง', 'name_en' => 'Room'])->assertForbidden();
        $this->assertSame(0, \App\Models\PresentationRoom::where('name_th', 'ห้อง')->count());
        $this->actingAs($admin)->post('/admin/presentations/rooms', ['name_th' => 'ห้อง 301', 'name_en' => 'Room 301'])->assertSessionHasNoErrors();
        $room = \App\Models\PresentationRoom::where('name_th', 'ห้อง 301')->firstOrFail();
        $session = ['date' => $day, 'start_time' => '09:00', 'end_time' => '09:30', 'presentation_room_id' => $room->id, 'minutes' => 15];
        $this->actingAs($admin)->post('/admin/presentations/sessions', [...$session, 'date' => now()->addDays(40)->toDateString()])->assertSessionHasErrors('date'); // not a conference day
        $this->actingAs($admin)->post('/admin/presentations/sessions', $session)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/presentations/sessions', [...$session, 'start_time' => '09:15', 'end_time' => '10:00'])->assertSessionHasErrors('presentation_room_id'); // same room, overlapping
        $s = \App\Models\PresentationSession::latest('id')->firstOrFail();

        // Two places (30 min / 15): A 09:00, B 09:15; C is refused; editors assign from the paper page
        $this->actingAs($editor)->put("/admin/submissions/{$a->id}/presentation", ['session_id' => $s->id])->assertSessionHasNoErrors();
        $this->actingAs($editor)->put("/admin/submissions/{$b->id}/presentation", ['session_id' => $s->id])->assertSessionHasNoErrors();
        $this->actingAs($editor)->put("/admin/submissions/{$c->id}/presentation", ['session_id' => $s->id])->assertSessionHasErrors('session_id');
        $this->assertSame(['09:00', '09:15'], [$a->fresh()->presentation_at->format('H:i'), $b->fresh()->presentation_at->format('H:i')]);

        // Reorder: B first; take A out: B stays first
        $this->actingAs($admin)->put("/admin/presentations/papers/{$b->id}/move", ['direction' => 'up']);
        $this->assertSame('09:00', $b->fresh()->presentation_at->format('H:i'));
        $this->actingAs($admin)->put("/admin/submissions/{$a->id}/presentation", ['session_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->presentation_at);

        // The session moves to 13:00: its papers follow; the public schedule shows it
        $this->actingAs($admin)->put("/admin/presentations/sessions/{$s->id}", [...$session, 'start_time' => '13:00', 'end_time' => '13:30'])->assertSessionHasNoErrors();
        $this->assertSame('13:00', $b->fresh()->presentation_at->format('H:i'));
        $this->get('/schedule')->assertOk()->assertInertia(fn ($page) => $page->where('presentations', fn ($list) => collect($list)->contains(fn ($x) => $x['id'] === $s->id && $x['papers'][0]['code'] === $b->code)));
        $this->actingAs($admin)->get('/admin/presentations')->assertOk()->assertInertia(fn ($page) => $page->where('waiting', fn ($w) => collect($w)->pluck('id')->contains($a->id)));

        // A room in use cannot be deleted; deleting the session sends its papers back to the waiting list
        $this->actingAs($admin)->delete("/admin/presentations/rooms/{$room->id}")->assertForbidden();
        // Presentation days are kept on the page: a day with a session cannot go; any other day can be added
        $this->actingAs($admin)->delete("/admin/presentations/days/{$day}")->assertSessionHas('error');
        $later = now()->addDays(40)->toDateString();
        $this->actingAs($admin)->post('/admin/presentations/days', ['date' => $later])->assertSessionHasNoErrors();
        $this->assertContains($later, \App\Http\Controllers\Admin\PresentationController::days());
        $this->actingAs($admin)->post('/admin/presentations/sessions', [...$session, 'date' => $later])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete("/admin/presentations/sessions/{$s->id}")->assertSessionHasNoErrors();
        $this->assertNull($b->fresh()->presentation_session_id);
        $this->actingAs($admin)->delete("/admin/presentations/days/{$day}")->assertSessionHas('success');
        $this->assertNotContains($day, \App\Http\Controllers\Admin\PresentationController::days());
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
